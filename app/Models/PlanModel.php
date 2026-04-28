<?php

namespace App\Models;

use App\Core\Database;

class PlanModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getModelsByPlan(int $planId): array
    {
        return $this->db->query(
            "SELECT mc.* FROM model_configs mc INNER JOIN plan_models pm ON mc.id = pm.model_id WHERE pm.plan_id = ? ORDER BY mc.sort_order ASC",
            [$planId]
        );
    }

    public function getModelIdsByPlan(int $planId): array
    {
        $rows = $this->db->query("SELECT model_id FROM plan_models WHERE plan_id = ?", [$planId]);
        return array_map(function($r) { return (int)$r['model_id']; }, $rows);
    }

    public function setModelsForPlan(int $planId, array $modelIds): void
    {
        $this->db->execute("DELETE FROM plan_models WHERE plan_id = ?", [$planId]);
        foreach ($modelIds as $mid) {
            $this->db->insert('plan_models', ['plan_id' => $planId, 'model_id' => (int)$mid]);
        }
    }

    public function canUseModel(int $planId, int $modelId): bool
    {
        $r = $this->db->queryOne(
            "SELECT id FROM plan_models WHERE plan_id = ? AND model_id = ?",
            [$planId, $modelId]
        );
        return !empty($r);
    }
}
