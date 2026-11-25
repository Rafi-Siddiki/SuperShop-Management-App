<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\DB;

trait RetriesTransactions
{
    /**
     * Run the callback in a transaction, retrying up to 3 times if MySQL picks
     * it as a deadlock victim. Laravel only retries at the outermost level, so
     * nested calls simply join the caller's transaction. Callbacks must not
     * have side effects outside the database (they may run more than once).
     */
    protected function atomic(callable $callback): mixed
    {
        return DB::transaction($callback, 3);
    }
}
