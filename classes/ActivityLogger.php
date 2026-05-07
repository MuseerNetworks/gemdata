<?php

declare(strict_types=1);

namespace GemData\Classes;

class ActivityLogger
{
    public function __construct(private Database $db)
    {
    }

    public function log(string $actorType, int $actorId, string $action, string $description, array $meta = []): void
    {
        $this->db->execute(
            'INSERT INTO activity_logs (actor_type, actor_id, action, description, meta_json)
             VALUES (:actor_type, :actor_id, :action, :description, :meta_json)',
            [
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'description' => $description,
                'meta_json' => $meta === [] ? null : json_encode($meta),
            ]
        );
    }
}
