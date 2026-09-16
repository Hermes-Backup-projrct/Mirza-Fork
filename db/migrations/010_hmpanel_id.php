<?php

/**
 * Adds the `hmpanel_id` column to marzban_panel.
 *
 * HMPanel is a NestJS layer in front of 3x-ui. One Mirza "location" maps to one
 * panel registered inside HMPanel, addressed by its Prisma UUID — not by the
 * 3x-ui URL alone. The UUID is looked up via GET /api/panels when the panel is
 * added, and stored here so inbound/client calls do not need to re-scan.
 *
 * Column is nullable: only rows with type = 'hmpanel' use it.
 */
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('marzban_panel')) {
        return;
    }
    if ($schema->hasColumn('marzban_panel', 'hmpanel_id')) {
        return;
    }
    $pdo->exec('ALTER TABLE marzban_panel ADD COLUMN hmpanel_id VARCHAR(100) NULL');
};
