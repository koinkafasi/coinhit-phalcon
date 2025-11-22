<?php

namespace Tahmin\Controllers;

use Tahmin\Models\Coupon\Coupon;
use Tahmin\Models\Prediction\Prediction;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;

class ExportController extends BaseController
{
    /**
     * Export coupon to PDF
     */
    public function couponPdfAction(string $id)
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $coupon = Coupon::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $id]
        ]);

        if (!$coupon) {
            return $this->sendError('Coupon not found', 404);
        }

        if ($coupon->user_id !== $this->currentUser->id && !$coupon->is_shared) {
            return $this->sendError('Access denied', 403);
        }

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Tahmin1x2');
        $pdf->SetAuthor('Tahmin1x2');
        $pdf->SetTitle('Kupon #' . substr($id, 0, 8));

        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Header
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, 'Tahmin1x2 Kupon', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Kupon ID: ' . $id, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Tarih: ' . $coupon->created_at->format('d.m.Y H:i'), 0, 1, 'C');
        $pdf->Ln(5);

        // Coupon info
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'Kupon Bilgileri', 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        $html = '<table border="1" cellpadding="4">
            <tr>
                <td><b>Tip:</b></td>
                <td>' . ucfirst($coupon->coupon_type) . '</td>
                <td><b>Bahis:</b></td>
                <td>' . number_format($coupon->stake, 2) . ' TL</td>
            </tr>
            <tr>
                <td><b>Toplam Oran:</b></td>
                <td>' . number_format($coupon->total_odds, 2) . '</td>
                <td><b>Tahmini Kazanç:</b></td>
                <td>' . number_format($coupon->potential_win, 2) . ' TL</td>
            </tr>
            <tr>
                <td><b>Durum:</b></td>
                <td>' . $this->getStatusText($coupon->status) . '</td>
                <td><b>Kar/Zarar:</b></td>
                <td style="color:' . ($coupon->profit_loss >= 0 ? 'green' : 'red') . '">'
                    . number_format($coupon->profit_loss, 2) . ' TL</td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(5);

        // Picks
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'Seçimler', 0, 1);

        $picksHtml = '<table border="1" cellpadding="3">
            <tr style="background-color:#f0f0f0;">
                <th width="5%">#</th>
                <th width="40%">Maç</th>
                <th width="20%">Tahmin</th>
                <th width="15%">Oran</th>
                <th width="20%">Sonuç</th>
            </tr>';

        $picks = $coupon->getPicks();
        $index = 1;
        foreach ($picks as $pick) {
            $prediction = $pick->getPrediction();
            $match = $prediction->getMatch();

            $picksHtml .= '<tr>
                <td>' . $index++ . '</td>
                <td>' . $match->getHomeTeam()->name . ' vs ' . $match->getAwayTeam()->name . '</td>
                <td>' . $prediction->predicted_result . '</td>
                <td>' . number_format($pick->odds, 2) . '</td>
                <td>' . $this->getStatusText($pick->status) . '</td>
            </tr>';
        }

        $picksHtml .= '</table>';
        $pdf->writeHTML($picksHtml, true, false, true, false, '');

        // QR Code for sharing
        if ($coupon->share_code) {
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 5, 'Kuponu Paylaş:', 0, 1);

            // TODO: Generate QR code
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, 'https://tahmin1x2.com/coupon/' . $coupon->share_code, 0, 1);
        }

        $pdf->Output('kupon-' . substr($id, 0, 8) . '.pdf', 'D');
        exit;
    }

    /**
     * Export predictions to Excel
     */
    public function predictionsExcelAction()
    {
        if (!$this->currentUser || !$this->currentUser->isPremiumUser()) {
            return $this->sendError('Premium subscription required', 403);
        }

        $startDate = $this->request->getQuery('start_date', 'string', date('Y-m-d', strtotime('-7 days')));
        $endDate = $this->request->getQuery('end_date', 'string', date('Y-m-d'));

        $predictions = Prediction::find([
            'conditions' => 'created_at BETWEEN :start: AND :end:',
            'bind' => [
                'start' => $startDate . ' 00:00:00',
                'end' => $endDate . ' 23:59:59'
            ],
            'order' => 'created_at DESC'
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->setCellValue('A1', 'Tarih');
        $sheet->setCellValue('B1', 'Maç');
        $sheet->setCellValue('C1', 'Tahmin Tipi');
        $sheet->setCellValue('D1', 'Tahmin');
        $sheet->setCellValue('E1', 'Güven Skoru');
        $sheet->setCellValue('F1', 'Oran');
        $sheet->setCellValue('G1', 'Sonuç');
        $sheet->setCellValue('H1', 'Gerçek Sonuç');

        // Style header
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ]
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Data
        $row = 2;
        foreach ($predictions as $prediction) {
            $match = $prediction->getMatch();

            $sheet->setCellValue('A' . $row, $prediction->created_at->format('d.m.Y H:i'));
            $sheet->setCellValue('B' . $row, $match->getHomeTeam()->name . ' vs ' . $match->getAwayTeam()->name);
            $sheet->setCellValue('C' . $row, $prediction->prediction_type);
            $sheet->setCellValue('D' . $row, $prediction->predicted_result);
            $sheet->setCellValue('E' . $row, $prediction->confidence_score . '%');
            $sheet->setCellValue('F' . $row, $prediction->actual_odds ?? '-');
            $sheet->setCellValue('G' . $row, $this->getStatusText($prediction->status));
            $sheet->setCellValue('H' . $row, $prediction->actual_result ?? '-');

            // Color code results
            if ($prediction->status === Prediction::STATUS_WON) {
                $sheet->getStyle('G' . $row)->getFont()->getColor()->setRGB('008000');
            } elseif ($prediction->status === Prediction::STATUS_LOST) {
                $sheet->getStyle('G' . $row)->getFont()->getColor()->setRGB('FF0000');
            }

            $row++;
        }

        // Auto-size columns
        foreach(range('A','H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="tahminler-' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Export user statistics to PDF
     */
    public function userStatsPdfAction()
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $userId = $this->currentUser->id;
        $period = $this->request->getQuery('period', 'string', 'month');

        $startDate = $this->getStartDate($period);

        // Get user stats
        $coupons = Coupon::find([
            'conditions' => 'user_id = :user_id: AND created_at >= :start:',
            'bind' => [
                'user_id' => $userId,
                'start' => $startDate
            ]
        ]);

        $totalCoupons = count($coupons);
        $wonCoupons = 0;
        $lostCoupons = 0;
        $totalStake = 0;
        $totalWin = 0;
        $totalProfit = 0;

        foreach ($coupons as $coupon) {
            $totalStake += $coupon->stake;
            if ($coupon->status === Coupon::STATUS_WON) {
                $wonCoupons++;
                $totalWin += $coupon->actual_win;
            } elseif ($coupon->status === Coupon::STATUS_LOST) {
                $lostCoupons++;
            }
            $totalProfit += $coupon->profit_loss;
        }

        $winRate = $totalCoupons > 0 ? round(($wonCoupons / $totalCoupons) * 100, 2) : 0;
        $roi = $totalStake > 0 ? round(($totalProfit / $totalStake) * 100, 2) : 0;

        // Create PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Tahmin1x2');
        $pdf->SetTitle('İstatistiklerim');
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'Tahmin1x2 - İstatistiklerim', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, $this->currentUser->full_name ?: $this->currentUser->email, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Periyot: ' . $this->getPeriodText($period), 0, 1, 'C');
        $pdf->Ln(5);

        // Statistics
        $html = '<table border="1" cellpadding="5" style="width:100%">
            <tr style="background-color:#f0f0f0;">
                <th colspan="2">Genel İstatistikler</th>
            </tr>
            <tr>
                <td><b>Toplam Kupon:</b></td>
                <td>' . $totalCoupons . '</td>
            </tr>
            <tr>
                <td><b>Kazanan Kupon:</b></td>
                <td style="color:green">' . $wonCoupons . '</td>
            </tr>
            <tr>
                <td><b>Kaybeden Kupon:</b></td>
                <td style="color:red">' . $lostCoupons . '</td>
            </tr>
            <tr>
                <td><b>Başarı Oranı:</b></td>
                <td><b>' . $winRate . '%</b></td>
            </tr>
            <tr style="background-color:#f0f0f0;">
                <th colspan="2">Finansal Özet</th>
            </tr>
            <tr>
                <td><b>Toplam Bahis:</b></td>
                <td>' . number_format($totalStake, 2) . ' TL</td>
            </tr>
            <tr>
                <td><b>Toplam Kazanç:</b></td>
                <td style="color:green">' . number_format($totalWin, 2) . ' TL</td>
            </tr>
            <tr>
                <td><b>Net Kar/Zarar:</b></td>
                <td style="color:' . ($totalProfit >= 0 ? 'green' : 'red') . '"><b>'
                    . number_format($totalProfit, 2) . ' TL</b></td>
            </tr>
            <tr>
                <td><b>ROI (Yatırım Getirisi):</b></td>
                <td><b>' . $roi . '%</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->Output('istatistiklerim-' . date('Y-m-d') . '.pdf', 'D');
        exit;
    }

    // Helper methods
    private function getStatusText(string $status): string
    {
        $statuses = [
            'pending' => 'Beklemede',
            'won' => 'Kazandı',
            'lost' => 'Kaybetti',
            'void' => 'İptal',
            'partially_won' => 'Kısmen Kazandı'
        ];

        return $statuses[$status] ?? $status;
    }

    private function getStartDate(string $period): string
    {
        switch ($period) {
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'year':
                return date('Y-m-d 00:00:00', strtotime('-365 days'));
            default:
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
    }

    private function getPeriodText(string $period): string
    {
        $periods = [
            'week' => 'Son 7 Gün',
            'month' => 'Son 30 Gün',
            'year' => 'Son 1 Yıl'
        ];

        return $periods[$period] ?? 'Son 30 Gün';
    }
}
