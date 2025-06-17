<?php

namespace OnaOnbir\OOAutoWeave;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use OnaOnbir\OOAutoWeave\Core\Console\Commands\RunScheduledTriggers;
use OnaOnbir\OOAutoWeave\Core\DTO\TriggerHandlerResult;
use OnaOnbir\OOAutoWeave\Core\Registry\ActionRegistry;
use OnaOnbir\OOAutoWeave\Core\Registry\TriggerRegistry;
use OnaOnbir\OOAutoWeave\Core\Support\Logger;
use OnaOnbir\OOAutoWeave\Jobs\DispatchTriggerExecutionJob;
use OnaOnbir\OOAutoWeave\Models\Trigger;

class OOAutoWeaveServiceProvider extends ServiceProvider
{
    private string $packageName = 'oo-auto-weave';

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->packageBooted();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/'.$this->packageName.'.php',
            $this->packageName
        );

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], $this->packageName.'-migrations');

        $this->publishes([
            __DIR__.'/../config/'.$this->packageName.'.php' => config_path($this->packageName.'.php'),
        ], $this->packageName.'-config');

        $this->commands([
            //
        ]);

        $this->registerConfiguredEventListeners();
    }

    public function packageBooted(): void
    {
        $this->registerDefaultTriggers();
        $this->registerDefaultActions();
        $this->registerConfiguredEventListeners();
        $this->registerJobEventListeners();
        $this->registerEventTriggerListeners();
    }

    public function packageRegistered(): void
    {
        $this->commands([
            RunScheduledTriggers::class,
        ]);
    }

    private function registerDefaultActions(): void
    {
        ActionRegistry::register(
            type: 'test_log_action',
            executor: \OnaOnbir\OOAutoWeave\Execution\Handlers\LogAction::class,
            options: [
                'label' => 'Log Aksiyonu',
                'description' => 'Bir log kaydı oluşturur.',
                'form_fields' => [
                    [
                        'key' => 'title',
                        'label' => 'Log Başlığı',
                        'type' => 'text',
                        'hint' => 'Oluşturulacak log kaydının başlığı.',
                    ],
                    [
                        'key' => 'message',
                        'label' => 'Log Mesajı',
                        'type' => 'textarea',
                        'hint' => 'Oluşturulacak log kaydının mesaj içeriği.',
                    ],
                ],
            ]
        );
    }

    protected function registerDefaultTriggers(): void
    {
        // RECORD UPDATED TRIGGER
        TriggerRegistry::register(
            key: 'model_changes',
            group: 'model',
            type: 'record_updated',
            handler: function (\OnaOnbir\OOAutoWeave\Models\Trigger $trigger, array $context = []): TriggerHandlerResult {
                $model = $context['model'] ?? null;
                $source = 'Status Change Handler';

                Logger::info('Trigger handler started', [
                    'trigger_id' => $trigger->id,
                    'trigger_key' => $trigger->key,
                    'model' => $model ? get_class($model) : 'null',
                ], $source);

                if (! $model) {
                    Logger::info('No model found in context — trigger denied', [
                        'trigger_id' => $trigger->id,
                    ], $source);

                    return TriggerHandlerResult::deny();
                }

                $changed = $context['attributes']['changedAttributes'] ?? $model->getChanges();
                $field = $trigger->settings['field'] ?? null;

                Logger::info('Changed attributes and trigger field', [
                    'changed' => $changed,
                    'expected_field' => $field,
                ], $source);

                if ($field && ! array_key_exists($field, $changed)) {
                    Logger::info('Watched field not changed — trigger denied', [
                        'field' => $field,
                    ], $source);

                    return TriggerHandlerResult::deny();
                }

                Logger::info('Trigger allowed', [
                    'trigger_id' => $trigger->id,
                    'model_id' => $model->getKey(),
                ], $source);

                return TriggerHandlerResult::allow($trigger, $context);
            },
            options: [
                'is_model_trigger' => true,
                'label' => 'Kayıt Güncellendiğinde',
                'description' => 'Bir kayıt güncellendiğinde tetiklenir.',
                'fields' => [
                    [
                        'key' => 'model',
                        'label' => 'Model Seçiniz',
                        'type' => 'select',
                        'options' => oo_wa_automation_get_eligible_models(),
                        'hint' => 'Bu otomasyonun dinleyeceği model sınıfını seçiniz.',
                    ],
                    [
                        'key' => 'field',
                        'label' => 'Takip Edilecek Alan',
                        'type' => 'text',
                        'hint' => 'Değişimi izlenecek alanın veri anahtarını giriniz (örnek: status)',
                    ],
                ],
                'icon' => 'refresh-cw',
                'category' => 'Kayıt Olayları',
            ]
        );

        // RECORD DELETED
        TriggerRegistry::register(
            key: 'model_changes',
            group: 'model',
            type: 'record_deleted',
            handler: function (\OnaOnbir\OOAutoWeave\Models\Trigger $trigger, array $context = []): TriggerHandlerResult {
                $source = 'Delete Handler';

                $modelClass = $trigger->settings['model'] ?? null;
                $deletedAttributes = $context['attributes'] ?? [];

                Logger::info('Delete trigger handler started', [
                    'trigger_id' => $trigger->id,
                    'trigger_key' => $trigger->key,
                    'expected_model' => $modelClass,
                    'deleted_attributes' => $deletedAttributes,
                ], $source);

                // ID kontrolü için (model_id zorunlu olarak geliyor)
                $modelId = $context['model_id'] ?? null;

                if (! $modelClass || empty($deletedAttributes)) {
                    Logger::info('Eksik model veya attribute bilgisi — trigger denied', [], $source);

                    return TriggerHandlerResult::deny();
                }

                Logger::info('Delete trigger allowed', [
                    'trigger_id' => $trigger->id,
                    'model_id' => $modelId,
                    'deleted_attributes' => $deletedAttributes,
                ], $source);

                return TriggerHandlerResult::allow($trigger, $context);
            },
            options: [
                'is_model_trigger' => true,
                'label' => 'Kayıt Silindiğinde',
                'description' => 'Bir kayıt silindiğinde tetiklenir.',
                'fields' => [
                    [
                        'key' => 'model',
                        'label' => 'Model Seçiniz',
                        'type' => 'select',
                        'options' => oo_wa_automation_get_eligible_models(),
                        'hint' => 'Bu otomasyonun dinleyeceği model sınıfını seçiniz.',
                    ],
                ],
                'icon' => 'trash-2',
                'category' => 'Kayıt Olayları',
            ]
        );

        // SCHEDULED TRIGGER
        TriggerRegistry::register(
            key: 'scheduled_automation',
            group: 'time',
            type: 'scheduled',
            handler: function (Model $trigger, array $context = []) {
                // Koşul vs. varsa burada kontrol edilir
                return TriggerHandlerResult::allow(trigger: $trigger, context: $context);
            },
            options: [
                'label' => 'Zamanlı Çalıştır',
                'description' => 'Zamanlanmış bir görevi tetikler.',
                'fields' => [
                    [
                        'key' => 'cron',
                        'label' => 'Cron ifadesi',
                        'type' => 'text',
                        'hint' => 'Örnek: 0 9 * * * (her gün 09:00)',
                    ],
                ],
                'icon' => 'clock',
                'category' => 'Zamanlayıcı',
            ]
        );

        // EVENT TRIGGER
        TriggerRegistry::register(
            key: 'event_trigger',
            group: 'event',
            type: 'class_fired',
            handler: function (\OnaOnbir\OOAutoWeave\Models\Trigger $trigger, array $context = []): TriggerHandlerResult {
                // Koşul vs. varsa burada kontrol edilir
                return TriggerHandlerResult::allow($trigger, $context);
            },
            options: [
                'label' => 'Event Sınıfı ile Tetikle',
                'description' => 'Belirtilen Laravel event sınıfı tetiklendiğinde çalışır.',
                'fields' => [
                    [
                        'key' => 'event',
                        'label' => 'Event Sınıfı',
                        'type' => 'text',
                        'hint' => 'Tam sınıf adını girin (örnek: App\\Events\\UserStatusChanged)',
                    ],
                ],
                'icon' => 'zap',
                'category' => 'Event Tetikleyiciler',
            ]
        );

        // JOB WATCHER
        TriggerRegistry::register(
            key: 'job_event',
            group: 'system',
            type: 'job_status',
            handler: function (Trigger $trigger, array $context = []): TriggerHandlerResult {
                $watchedJob = $trigger->settings['job'] ?? null;
                $status = $trigger->settings['status'] ?? null;
                $jobClass = $context['attributes']['job_class'] ?? null;
                $eventStatus = $context['attributes']['status'] ?? null;

                if ($watchedJob !== $jobClass || $status !== $eventStatus) {
                    return TriggerHandlerResult::deny();
                }

                return TriggerHandlerResult::allow($trigger, $context);
            },
            options: [
                'label' => 'Job Durum Takibi',
                'description' => 'Bir job tamamlandığında veya başarısız olduğunda tetiklenir.',
                'fields' => [
                    [
                        'key' => 'job',
                        'label' => 'Takip Edilen Job',
                        'type' => 'text',
                        'hint' => 'Tam sınıf adı (örn: App\\Jobs\\ProcessSomething)',
                    ],
                    [
                        'key' => 'status',
                        'label' => 'İstenilen Durum',
                        'type' => 'select',
                        'options' => [
                            'completed' => 'Tamamlandı',
                            'failed' => 'Hata Aldı',
                        ],
                    ],
                ],
                'icon' => 'activity',
                'category' => 'Job Tabanlı Tetikleyiciler',
            ]
        );

        // MANUAL TRIGGER
        TriggerRegistry::register(
            key: 'manual',
            group: 'custom',
            type: 'manual',
            handler: fn (Trigger $trigger, array $context = []) => TriggerHandlerResult::allow($trigger, $context),
            options: [
                'label' => 'Elle Tetikleme',
                'description' => 'Elle tetiklenebilen otomasyon.',
                'icon' => 'zap',
                'category' => 'Diğer',
            ]
        );

        //        TriggerRegistry::register(
        //            key: 'record_created',
        //            type: 'model',
        //            handler: function (\OnaOnbir\OOAutoWeave\Models\Trigger $trigger, array $context = []): TriggerHandlerResult {
        //                $model = $context['model'] ?? null;
        //
        //                if (! $model) return TriggerHandlerResult::deny();
        //
        //                \Illuminate\Support\Facades\Log::info('[OOAutoWeave::PROVIDER::record_created]', [
        //                    'trigger_id' => $trigger->id,
        //                    'model' => get_class($model),
        //                    'model_id' => $model->getKey(),
        //                ]);
        //
        //                return TriggerHandlerResult::allow($trigger, $context);
        //            },
        //            options: [
        //                'is_model_trigger' => true,
        //                'label' => 'Kayıt Oluşturulduğunda',
        //                'description' => 'Bir model oluşturulduğunda tetiklenir.',
        //                'fields' => [
        //                    [
        //                        'key' => 'model',
        //                        'label' => 'Model Seçiniz',
        //                        'type' => 'select',
        //                        'options' => oo_wa_automation_get_eligible_models(),
        //                        'hint' => 'Bu otomasyonun dinleyeceği model sınıfını seçiniz.',
        //                    ],
        //                ],
        //                'icon' => 'plus-square',
        //                'category' => 'Kayıt Olayları',
        //            ]
        //        );

        //        TriggerRegistry::register(
        //            key: 'record_deleted',
        //            type: 'model',
        //            handler: function (\OnaOnbir\OOAutoWeave\Models\Trigger $trigger, array $context = []): TriggerHandlerResult {
        //                $model = $context['model'] ?? null;
        //
        //                if (! $model) return TriggerHandlerResult::deny();
        //
        //                \Illuminate\Support\Facades\Log::info('[OOAutoWeave::PROVIDER::record_deleted]', [
        //                    'trigger_id' => $trigger->id,
        //                    'model' => get_class($model),
        //                    'model_id' => $model->getKey(),
        //                ]);
        //
        //                return TriggerHandlerResult::allow($trigger, $context);
        //            },
        //            options: [
        //                'is_model_trigger' => true,
        //                'label' => 'Kayıt Silindiğinde',
        //                'description' => 'Bir model silindiğinde tetiklenir.',
        //                'fields' => [
        //                    [
        //                        'key' => 'model',
        //                        'label' => 'Model Seçiniz',
        //                        'type' => 'select',
        //                        'options' => oo_wa_automation_get_eligible_models(),
        //                        'hint' => 'Bu otomasyonun dinleyeceği model sınıfını seçiniz.',
        //                    ],
        //                ],
        //                'icon' => 'trash-2',
        //                'category' => 'Kayıt Olayları',
        //            ]
        //        );

    }

    protected function registerEventTriggerListeners(): void
    {
        if (! app()->runningInConsole() || ! Schema::hasTable('oo_wa_triggers')) {
            return;
        }

        Trigger::query()
            ->active()
            ->where('key', 'event_trigger')
            ->where('group', 'event')
            ->where('type', 'class_fired')
            ->get()
            ->each(function (Trigger $trigger) {
                $eventClass = $trigger->settings['event'] ?? null;

                if (! $eventClass || ! class_exists($eventClass)) {
                    return;
                }

                Event::listen($eventClass, function ($event) use ($trigger) {
                    DispatchTriggerExecutionJob::dispatch($trigger, [
                        'source' => 'event.dynamic',
                        'attributes' => method_exists($event, 'toArray') ? $event->toArray() : (array) $event,
                    ]);
                });
            });
    }

    protected function registerJobEventListeners(): void
    {
        if (! app()->runningInConsole() || ! Schema::hasTable('oo_wa_triggers')) {
            return;
        }

        Event::listen(\Illuminate\Queue\Events\JobProcessed::class, function ($event) {
            $jobClass = method_exists($event->job, 'resolveName')
                ? $event->job->resolveName()
                : get_class($event->job);

            if ($jobClass === \OnaOnbir\OOAutoWeave\Jobs\DispatchTriggerExecutionJob::class) {
                return;
            }

            DispatchTriggerExecutionJob::dispatchForKey('job_event', [
                'attributes' => [
                    'job_class' => $jobClass,
                    'status' => 'completed',
                ],
            ]);
        });

        Event::listen(\Illuminate\Queue\Events\JobFailed::class, function ($event) {
            $jobClass = method_exists($event->job, 'resolveName')
                ? $event->job->resolveName()
                : get_class($event->job);
            if ($jobClass === \OnaOnbir\OOAutoWeave\Jobs\DispatchTriggerExecutionJob::class) {
                return;
            }

            DispatchTriggerExecutionJob::dispatchForKey('job_event', [
                'attributes' => [
                    'job_class' => $jobClass,
                    'status' => 'completed',
                ],
            ]);
        });
    }

    protected function registerConfiguredEventListeners(): void
    {
        $listeners = config($this->packageName.'.event_listeners', []);
        foreach ($listeners as $event => $handlers) {
            foreach ((array) $handlers as $handler) {
                Event::listen($event, $handler);
            }
        }
    }
}
