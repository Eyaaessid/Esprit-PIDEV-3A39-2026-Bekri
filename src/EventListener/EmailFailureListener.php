<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;

/**
 * Logs email sending attempts, successes, and failures for debugging.
 */
class EmailFailureListener implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => 'onMessage',
            SentMessageEvent::class => 'onSent',
            FailedMessageEvent::class => 'onFailed',
        ];
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $details = $this->extractMessageDetails($message);
        $details['transport'] = $event->getTransport();
        $details['queued'] = $event->isQueued();
        $this->logger->info('Email send attempt', $details);
    }

    public function onSent(SentMessageEvent $event): void
    {
        $sent = $event->getMessage();
        $original = $sent->getOriginalMessage();
        $details = $this->extractMessageDetails($original);
        $details['message_id'] = $sent->getMessageId();
        $this->logger->info('Email sent successfully', $details);
    }

    public function onFailed(FailedMessageEvent $event): void
    {
        $error = $event->getError();
        $message = $event->getMessage();
        $details = $this->extractMessageDetails($message);
        $details['error'] = $error->getMessage();
        $details['error_class'] = \get_class($error);
        $this->logger->error('Email failed to send', $details);
    }

    /**
     * @return array{message_class:string,to?:array,from?:array,subject?:string}
     */
    private function extractMessageDetails(\Symfony\Component\Mime\RawMessage $message): array
    {
        $details = ['message_class' => \get_class($message)];

        if ($message instanceof \Symfony\Component\Mime\Email) {
            $details['to'] = array_map(static fn($a) => $a->getAddress(), $message->getTo());
            $details['from'] = array_map(static fn($a) => $a->getAddress(), $message->getFrom());
            $details['subject'] = $message->getSubject();
        }

        return $details;
    }
}
