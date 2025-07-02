<?php

namespace OnaOnbir\OOAutoWeave\Core\DefaultNodes;

use Illuminate\Support\Facades\Mail;
use OnaOnbir\OOAutoWeave\Core\ContextManager;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\BaseNodeHandler;
use OnaOnbir\OOAutoWeave\Core\NodeHandler\NodeHandlerResult;

class SendRawEmailNode extends BaseNodeHandler
{
    public function handle(array $node, ContextManager $manager): NodeHandlerResult
    {
        $to = $node['attributes']['to'] ?? null;
        $subject = $node['attributes']['subject'] ?? 'Notification';
        $message = $node['attributes']['message'] ?? 'No message provided.';

        if (empty($to)) {
            return NodeHandlerResult::error([], [], 'Recipient email (to) is required.');
        }

        try {
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });

            return NodeHandlerResult::success([
                'status' => 'email_sent_successfully',
                'to' => $to,
                'subject' => $subject,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return NodeHandlerResult::error([], [], 'Mail error: '.$e->getMessage());
        }
    }

    public static function definition(): array
    {
        return [
            'type' => 'send_raw_email',
            'attributes' => ['icon' => 'mail'],
        ];
    }
}
