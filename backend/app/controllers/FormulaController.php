<?php

namespace Tahmin\Controllers;

use Tahmin\Models\Formula;

class FormulaController extends BaseController
{
    /**
     * Get user's formulas
     */
    public function indexAction()
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $formulas = Formula::find([
            'conditions' => 'user_id = :user_id: OR is_public = true',
            'bind' => ['user_id' => $this->currentUser->id],
            'order' => 'created_at DESC'
        ]);

        return $this->sendSuccess(['formulas' => $formulas->toArray()]);
    }

    /**
     * Create formula
     */
    public function createAction()
    {
        if (!$this->currentUser || !$this->currentUser->isPremiumUser()) {
            return $this->sendError('Premium subscription required', 403);
        }

        $data = $this->request->getJsonRawBody(true);

        $formula = new Formula();
        $formula->user_id = $this->currentUser->id;
        $formula->name = $data['name'];
        $formula->description = $data['description'] ?? null;
        $formula->rules = $data['rules'] ?? [];
        $formula->filters = $data['filters'] ?? [];
        $formula->is_public = $data['is_public'] ?? false;

        if (!$formula->save()) {
            return $this->sendError('Failed to create formula', 400);
        }

        return $this->sendSuccess(['formula' => $formula->toArray()], 'Formula created', 201);
    }

    /**
     * Update formula
     */
    public function updateAction(int $id)
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $formula = Formula::findFirst($id);
        if (!$formula) {
            return $this->sendError('Formula not found', 404);
        }

        if ($formula->user_id !== $this->currentUser->id) {
            return $this->sendError('Access denied', 403);
        }

        $data = $this->request->getJsonRawBody(true);

        if (isset($data['name'])) $formula->name = $data['name'];
        if (isset($data['description'])) $formula->description = $data['description'];
        if (isset($data['rules'])) $formula->rules = $data['rules'];
        if (isset($data['filters'])) $formula->filters = $data['filters'];
        if (isset($data['is_active'])) $formula->is_active = $data['is_active'];
        if (isset($data['is_public'])) $formula->is_public = $data['is_public'];

        if (!$formula->save()) {
            return $this->sendError('Failed to update formula', 400);
        }

        return $this->sendSuccess(['formula' => $formula->toArray()], 'Formula updated');
    }

    /**
     * Delete formula
     */
    public function deleteAction(int $id)
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        $formula = Formula::findFirst($id);
        if (!$formula) {
            return $this->sendError('Formula not found', 404);
        }

        if ($formula->user_id !== $this->currentUser->id) {
            return $this->sendError('Access denied', 403);
        }

        $formula->delete();

        return $this->sendSuccess(null, 'Formula deleted');
    }
}
