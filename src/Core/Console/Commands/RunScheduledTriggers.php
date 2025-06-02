<?php

namespace OnaOnbir\OOAutoWeave\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OnaOnbir\OOAutoWeave\Models\Trigger;
use OnaOnbir\OOAutoWeave\Jobs\DispatchTriggerExecutionJob;

class RunScheduledTriggers extends Command
{
    protected $signature = 'oo-auto-weave:run-scheduled';
    protected $description = 'Zamanlanmış otomasyon tetikleyicilerini çalıştırır.';

    public function handle(): void
    {
        $this->info('Zamanlanmış tetikleyiciler çalıştırılıyor...');

        $now = now()->toDateTimeString();

        $triggers = Trigger::query()
            ->active()
            ->where('type', 'scheduled')
            ->where('group', 'time')
            ->get();

        foreach ($triggers as $trigger) {
            DispatchTriggerExecutionJob::dispatch($trigger, [
                'source' => 'cron.schedule',
                'run_at' => $now,
            ]);
        }

        $this->info('İşlem tamamlandı.');
    }
}
