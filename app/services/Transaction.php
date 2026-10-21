<?php

declare(strict_types=1);

/**
 * Runs a unit of work atomically. If the caller already opened a transaction the
 * work simply joins it (the caller decides when to commit or roll back); otherwise
 * this opens one, commits on success and rolls back on any exception.
 */
final class Transaction
{
    public static function run(PDO $pdo, callable $work): mixed
    {
        $owns = !$pdo->inTransaction();
        if ($owns) {
            $pdo->beginTransaction();
        }

        try {
            $result = $work();
            if ($owns) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
