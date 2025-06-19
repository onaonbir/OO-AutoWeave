<?php

namespace OnaOnbir\OOAutoWeave;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use OnaOnbir\OOAutoWeave\Core\Console\Commands\RunScheduledTriggers;
use OnaOnbir\OOAutoWeave\Core\DTO\TriggerHandlerResult;
use OnaOnbir\OOAutoWeave\Core\Registry\ActionRegistry;
use OnaOnbir\OOAutoWeave\Core\Registry\FunctionRegistry;
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
        $this->registerFunctionRegisters();
    }

    public function packageRegistered(): void
    {
        $this->commands([
            RunScheduledTriggers::class,
        ]);
    }

    private function registerFunctionRegisters(): void
    {

        FunctionRegistry::register('json_encode', fn ($value, $options) => json_encode($value));

        FunctionRegistry::register('implode', function ($value, $options) {
            return is_array($value) ? implode($options['separator'] ?? ',', $value) : (string) $value;
        });

        FunctionRegistry::register('custom_function', fn ($value, $options) => '❗️TODO: örnek');

        FunctionRegistry::register('is_empty', fn($value) => empty($value));
        FunctionRegistry::register('is_numeric', fn($value) => is_numeric($value));
        FunctionRegistry::register('is_array', fn($value) => is_array($value));

        //HASH
        FunctionRegistry::register('md5', fn($value) => md5($value));
        FunctionRegistry::register('sha1', fn($value) => sha1($value));

        FunctionRegistry::register('uuid', fn() => (string) \Illuminate\Support\Str::uuid());
        FunctionRegistry::register('ulid', fn() => (string) \Illuminate\Support\Str::ulid());

        FunctionRegistry::register('starts_with', fn($value, $options) => str_starts_with($value, $options['needle'] ?? ''));
        FunctionRegistry::register('ends_with', fn($value, $options) => str_ends_with($value, $options['needle'] ?? ''));
        FunctionRegistry::register('contains', fn($value, $options) => str_contains($value, $options['needle'] ?? ''));

        FunctionRegistry::register('try', function ($value, $options) {
            $callback = $options['callback'] ?? null;

            try {
                return is_callable($callback) ? $callback($value) : $value;
            } catch (\Throwable $e) {
                return $options['catch'] ?? 'error';
            }
        });

        // STRING FUNCTIONS
        FunctionRegistry::register('upper', fn ($value) => strtoupper($value));
        FunctionRegistry::register('lower', fn ($value) => strtolower($value));
        FunctionRegistry::register('title', fn ($value) => ucwords($value));
        FunctionRegistry::register('trim', fn ($value) => trim($value));
        FunctionRegistry::register('substr', function ($value, $options) {
            $start = $options['start'] ?? 0;
            $length = $options['length'] ?? null;

            return substr($value, $start, $length);
        });
        FunctionRegistry::register('replace', function ($value, $options) {
            return str_replace($options['search'], $options['replace'], $value);
        });
        FunctionRegistry::register('slug', function ($value) {
            return Str::slug($value); // Laravel Str helper
        });
        FunctionRegistry::register('limit', function ($value, $options) {
            $limit = $options['limit'] ?? 10;
            return substr($value, 0, $limit);
        });

        // ARRAY FUNCTIONS
        FunctionRegistry::register('count', fn ($value) => is_array($value) ? count($value) : 1);
        FunctionRegistry::register('first', fn ($value) => is_array($value) ? reset($value) : $value);
        FunctionRegistry::register('last', fn ($value) => is_array($value) ? end($value) : $value);
        FunctionRegistry::register('unique', fn ($value) => is_array($value) ? array_unique($value) : [$value]);
        FunctionRegistry::register('sort', function ($value, $options) {
            if (! is_array($value)) {
                return [$value];
            }
            $sorted = $value;
            $direction = $options['direction'] ?? 'asc';
            $direction === 'desc' ? rsort($sorted) : sort($sorted);

            return $sorted;
        });
        FunctionRegistry::register('filter', function ($value, $options) {
            if (! is_array($value)) {
                return [];
            }
            $key = $options['key'] ?? null;
            $operator = $options['operator'] ?? '=';
            $filterValue = $options['value'] ?? null;

            return array_filter($value, function ($item) use ($key, $operator, $filterValue) {
                $itemValue = is_array($item) && $key ? ($item[$key] ?? null) : $item;

                return match ($operator) {
                    '=' => $itemValue == $filterValue,
                    '!=' => $itemValue != $filterValue,
                    '>' => $itemValue > $filterValue,
                    '<' => $itemValue < $filterValue,
                    'in' => in_array($itemValue, (array) $filterValue),
                    'contains' => str_contains($itemValue, $filterValue),
                    default => true
                };
            });
        });
        FunctionRegistry::register('pluck', function ($value, $options) {
            if (! is_array($value)) {
                return [];
            }
            $key = $options['key'] ?? 'name';

            return array_column($value, $key);
        });
        FunctionRegistry::register('chunk', function ($value, $options) {
            if (! is_array($value)) {
                return [$value];
            }
            $size = $options['size'] ?? 2;

            return array_chunk($value, $size);
        });
        FunctionRegistry::register('array_map', function ($value, $options = []) {
            if (!is_array($value)) return $value;

            $callback = $options['callback'] ?? null;

            // Fonksiyon adı string olarak geçilmişse, çağrılabilir mi kontrol et
            if (is_string($callback) && function_exists($callback)) {
                return array_map($callback, $value);
            }

            // Closure ise direkt kullan
            if (is_callable($callback)) {
                return array_map($callback, $value);
            }

            // Callback geçerli değilse orijinal veriyi döndür
            return $value;
        });

        // DATE FUNCTIONS
        FunctionRegistry::register('date_format', function ($value, $options) {
            $format = $options['format'] ?? 'Y-m-d';
            try {
                return Carbon::parse($value)->format($format);
            } catch (Exception $e) {
                return $value;
            }
        });
        FunctionRegistry::register('date_diff', function ($value, $options) {
            $from = $options['from'] ?? now();
            try {
                return Carbon::parse($from)->diffInDays(Carbon::parse($value));
            } catch (Exception $e) {
                return 0;
            }
        });
        FunctionRegistry::register('age', function ($value) {
            try {
                return Carbon::parse($value)->age;
            } catch (Exception $e) {
                return 0;
            }
        });

        // MATHEMATICAL FUNCTIONS
        FunctionRegistry::register('sum', fn ($value) => is_array($value) ? array_sum($value) : (float) $value);
        FunctionRegistry::register('avg', fn ($value) => is_array($value) ? array_sum($value) / count($value) : (float) $value);
        FunctionRegistry::register('min', fn ($value) => is_array($value) ? min($value) : $value);
        FunctionRegistry::register('max', fn ($value) => is_array($value) ? max($value) : $value);
        FunctionRegistry::register('round', function ($value, $options) {
            $precision = $options['precision'] ?? 0;

            return round((float) $value, $precision);
        });

        // FORMATTING FUNCTIONS
        FunctionRegistry::register('number_format', function ($value, $options) {
            $decimals = $options['decimals'] ?? 0;
            $decimal_sep = $options['decimal_separator'] ?? ',';
            $thousands_sep = $options['thousands_separator'] ?? '.';

            return number_format((float) $value, $decimals, $decimal_sep, $thousands_sep);
        });
        FunctionRegistry::register('currency', function ($value, $options) {
            $currency = $options['currency'] ?? '₺';
            $position = $options['position'] ?? 'after'; // before, after
            $formatted = number_format((float) $value, 2, ',', '.');

            return $position === 'before' ? $currency.$formatted : $formatted.' '.$currency;
        });
        FunctionRegistry::register('percentage', function ($value, $options) {
            $total = $options['total'] ?? 100;
            $precision = $options['precision'] ?? 1;
            $percentage = ((float) $value / (float) $total) * 100;

            return round($percentage, $precision).'%';
        });

        // CONDITIONAL FUNCTIONS
        FunctionRegistry::register('default', function ($value, $options) {
            $default = $options['default'] ?? '';

            return empty($value) ? $default : $value;
        });
        FunctionRegistry::register('conditional', function ($value, $options) {
            $condition = $options['condition'] ?? 'not_empty';
            $true_value = $options['true'] ?? $value;
            $false_value = $options['false'] ?? '';

            $result = match ($condition) {
                'not_empty' => ! empty($value),
                'empty' => empty($value),
                'equals' => $value == ($options['equals'] ?? null),
                'greater_than' => (float) $value > (float) ($options['than'] ?? 0),
                'less_than' => (float) $value < (float) ($options['than'] ?? 0),
                default => ! empty($value)
            };

            return $result ? $true_value : $false_value;
        });

        // EMAIL & CONTACT FUNCTIONS
        FunctionRegistry::register('email_domain', function ($value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return substr(strrchr($value, '@'), 1);
            }

            return '';
        });
        FunctionRegistry::register('phone_format', function ($value, $options) {
            $format = $options['format'] ?? 'international'; // national, international
            // Telefon formatlama logic'i burada
            $cleaned = preg_replace('/[^0-9]/', '', $value);
            if ($format === 'international' && ! str_starts_with($cleaned, '90')) {
                $cleaned = '90'.$cleaned;
            }

            return '+'.$cleaned;
        });

        // HTML & MARKUP FUNCTIONS
        FunctionRegistry::register('escape', fn ($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        FunctionRegistry::register('strip_tags', fn ($value) => strip_tags($value));
        FunctionRegistry::register('nl2br', fn ($value) => nl2br($value));
        FunctionRegistry::register('markdown', function ($value) {
            // Markdown parser kullanabilirsiniz
            return Str::markdown($value); // Laravel 9+
        });

        // FILE & URL FUNCTIONS
        FunctionRegistry::register('file_size', function ($value, $options) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $bytes = (int) $value;
            $i = 0;
            while ($bytes > 1024 && $i < count($units) - 1) {
                $bytes /= 1024;
                $i++;
            }

            return round($bytes, 2).' '.$units[$i];
        });
        FunctionRegistry::register('url_encode', fn ($value) => urlencode($value));
        FunctionRegistry::register('base64_encode', fn ($value) => base64_encode($value));

        // LOCALIZATION FUNCTIONS
        FunctionRegistry::register('trans', function ($value, $options) {
            $locale = $options['locale'] ?? app()->getLocale();

            return __($value, [], $locale);
        });
        FunctionRegistry::register('pluralize', function ($value, $options) {
            $count = $options['count'] ?? 1;

            return Str::plural($value, $count);
        });

        // ADVANCED FUNCTIONS
        FunctionRegistry::register('template', function ($value, $options) {
            $template = $options['template'] ?? '{value}';

            return str_replace('{value}', $value, $template);
        });
        FunctionRegistry::register('pipe', function ($value, $options) {
            $functions = $options['functions'] ?? [];
            $result = $value;
            foreach ($functions as $func) {
                $result = FunctionRegistry::call($func, $result, $options);
            }

            return $result;
        });

        // TÜRKÇE ÖZELLEŞTİRMELER
        FunctionRegistry::register('turkish_upper', function ($value) {
            $search = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü'];
            $replace = ['Ç', 'Ğ', 'I', 'Ö', 'Ş', 'Ü'];

            return str_replace($search, $replace, strtoupper($value));
        });
        FunctionRegistry::register('turkish_slug', function ($value) {
            $search = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'Ö', 'Ş', 'Ü'];
            $replace = ['c', 'g', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'o', 's', 'u'];

            return Str::slug(str_replace($search, $replace, $value));
        });

        // DEBUG FUNCTIONS
        FunctionRegistry::register('debug', function ($value, $options) {
            $format = $options['format'] ?? 'json';

            return match ($format) {
                'json' => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'var_dump' => print_r($value, true),
                'type' => gettype($value),
                default => (string) $value
            };
        });

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
        // RECORD CREATED TRIGGER
        TriggerRegistry::register(
            key: 'model_changes',
            group: 'model',
            type: 'record_created',
            handler: function (\OnaOnbir\OOAutoWeave\Models\Trigger $trigger, array $context = []): TriggerHandlerResult {
                $model = $context['model'] ?? null;
                $source = 'Created Trigger Handler';

                Logger::info('Created trigger handler started', [
                    'trigger_id' => $trigger->id,
                    'trigger_key' => $trigger->key,
                    'model' => $model ? get_class($model) : 'null',
                ], $source);

                if (! $model) {
                    Logger::warning('Model not found in context — trigger denied', [
                        'trigger_id' => $trigger->id,
                    ], $source);

                    return TriggerHandlerResult::deny();
                }

                $expectedModel = $trigger->settings['model'] ?? null;

                // Eğer trigger, belirli bir model sınıfı için tanımlandıysa onu doğrula
                if ($expectedModel && $expectedModel !== get_class($model)) {
                    Logger::info('Trigger model mismatch — denied', [
                        'expected' => $expectedModel,
                        'actual' => get_class($model),
                    ], $source);

                    return TriggerHandlerResult::deny();
                }

                Logger::info('Created trigger allowed', [
                    'trigger_id' => $trigger->id,
                    'model_id' => $model->getKey(),
                ], $source);

                return TriggerHandlerResult::allow($trigger, $context);
            },
            options: [
                'is_model_trigger' => true,
                'label' => 'Kayıt Oluşturulduğunda',
                'description' => 'Yeni bir kayıt oluşturulduğunda tetiklenir.',
                'fields' => [
                    [
                        'key' => 'model',
                        'label' => 'Model Seçiniz',
                        'type' => 'select',
                        'options' => oo_wa_automation_get_eligible_models(),
                        'hint' => 'Bu otomasyonun dinleyeceği model sınıfını seçiniz.',
                    ],
                ],
                'icon' => 'plus-circle',
                'category' => 'Kayıt Olayları',
            ]
        );

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
