<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultNodes;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeHandlerResult;

class SendRawEmailNode extends BaseNodeHandler
{
    public function handle(array $node, ContextManager $manager): NodeHandlerResult
    {
        $rawTo = $node['attributes']['to'] ?? null;

        $emails = is_array($rawTo)
            ? array_filter(array_map('trim', $rawTo))
            : (is_string($rawTo) ? array_map('trim', explode(',', $rawTo)) : []);

        $validEmails = array_filter($emails, function ($email) {
            return Validator::make(['email' => $email], ['email' => 'required|email'])->passes();
        });

        if (empty($validEmails)) {
            return NodeHandlerResult::error([], [], 'Hiç geçerli bir e-posta adresi bulunamadı.');
        }

        $subject = $node['attributes']['subject'] ?? 'Notification';
        $message = $node['attributes']['message'] ?? 'No message provided.';

        try {
            Mail::raw($message, function ($mail) use ($validEmails, $subject) {
                $mail->to($validEmails)->subject($subject);
            });

            return NodeHandlerResult::success([
                'sent_to' => $validEmails,
                'subject' => $subject,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return NodeHandlerResult::error([], [], 'Mail gönderilemedi: '.$e->getMessage());
        }
    }

    public static function definition(): array
    {
        return [
            'type' => 'send_raw_email',
            'attributes' => [
                'to' => '',
                'subject' => 'Notification',
                'message' => '',
                '__options__' => [
                    'label' => 'E-posta Gönder',
                    'description' => 'Belirtilen alıcıya basit bir düz metin e-posta gönderir.',
                    'form_fields' => [
                        [
                            'key' => 'to',
                            'label' => 'Alıcı E-posta',
                            'type' => 'input.array',
                            'required' => true,
                            'hint' => 'E-posta gönderilecek adres',
                        ],
                        [
                            'key' => 'subject',
                            'label' => 'Konu',
                            'type' => 'input',
                            'default' => 'Notification',
                            'hint' => 'E-posta başlığı',
                        ],
                        [
                            'key' => 'message',
                            'label' => 'Mesaj',
                            'type' => 'textarea',
                            'hint' => 'E-posta içeriği (düz metin)',
                        ],
                    ],
                ],
            ],
        ];
    }
}
