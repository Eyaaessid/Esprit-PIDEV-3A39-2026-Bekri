<?php

namespace App\Command;

use App\Service\EmotionalAnalysisService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:test-openai-key', description: 'Test OpenAI API key for emotional analysis')]
class TestOpenAIKeyCommand extends Command
{
    public function __construct(
        private readonly EmotionalAnalysisService $emotionalAnalysisService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Testing OpenAI API key (emotional analysis)');

        $io->text('Calling OpenAI API (test connection)...');
        $io->newLine();

        try {
            $result = $this->emotionalAnalysisService->testOpenAIConnection();
            $io->success('OpenAI API key is working.');
            $io->table(
                ['Field', 'Value'],
                [
                    ['emotion', $result->emotion],
                    ['risk_level', $result->riskLevel],
                    ['is_sensitive', $result->isSensitive ? 'yes' : 'no'],
                    ['matched_signals', implode(', ', $result->matchedSignals) ?: '(none)'],
                ]
            );
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('OpenAI request failed.');
            $io->section('Error details');
            $io->text('Message: ' . $e->getMessage());
            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                if ($response) {
                    $statusCode = $response->getStatusCode();
                    $io->text('HTTP status: ' . $statusCode);
                    if ($statusCode === 401) {
                        $io->warning('401 = Invalid API key, expired key, or key has extra spaces/newlines. Check .env: AI_API_KEY (no quotes, no space after =).');
                    }
                    if ($statusCode === 429) {
                        $io->warning('429 = Rate limit or no credits. Check your OpenAI billing at platform.openai.com.');
                    }
                    if ($statusCode === 403) {
                        $io->warning('403 = Forbidden. Key may be invalid or not allowed for this API.');
                    }
                }
            }
            $io->newLine();
            $io->text('Exception: ' . get_class($e));
            return Command::FAILURE;
        }
    }
}
