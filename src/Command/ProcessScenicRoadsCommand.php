<?php

namespace ScenicRoads\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use ScenicRoads\Pipeline\RoadEnricher;
use ScenicRoads\Source\SourceInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:roads:enrich',
    description: 'Extracts raw road data from sources and enriches it with Wikipedia, Wikidata, and AI-generated content.',
)]
class ProcessScenicRoadsCommand extends Command
{
    /** @var SourceInterface[] */
    private array $sources = [];

    public function __construct(
        iterable $sources,
        private RoadEnricher $enricher,
        private string $appName,
        private string $appVersion
    ) {
        $this->sources = iterator_to_array($sources);
        parent::__construct();
    }

    /**
     * The main entry point for the command logic.
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command requires a terminal that supports sections.');
        }

        $io = new SymfonyStyle($input, $output);
        $io->title("{$this->appName} v{$this->appVersion}");

        // 1. Select the Source
        $sourceNames = array_map(fn($s) => $s->name, $this->sources);
        $selectedName = $io->choice('Step 1: Select Source', $sourceNames);

        $source = current(array_filter($this->sources, fn($s) => $s->name === $selectedName));

        // 2. The source handles its own specific questions
        $targets = $source->getTargets($io);

        if (empty($targets)) {
            $io->warning('No targets selected.');
            return Command::SUCCESS;
        }

        // 3. Processing Loop
        $summary = $this->processTargets($targets, $source, $io, $output);

        // 4. Final Report
        $this->renderSummaryTable($summary, $io);

        return Command::SUCCESS;
    }

    /**
     * Iterates through the selected targets and triggers the enricher.
     * 
     * @param array $targets Selected targets to process (files, states...)
     * @param SourceInterface $source The selected source to get base data from.
     * @return array A collection of processing results for the final summary.
     */
    private function processTargets(
        array $targets,
        SourceInterface $source,
        SymfonyStyle $io,
        ConsoleOutputInterface $output
    ): array {
        $summary = [];
        $total = count($targets);

        $targetLog = $output->section();
        $enricherLog = $output->section();

        foreach ($targets as $index => $target) {
            $currentCount = $index + 1;

            $targetLog->overwrite(sprintf(
                "<info>%s: </info><comment>%s</comment> (%d/%d)",
                $source->name,
                $target,
                $currentCount,
                $total,
            ));

            try {
                $this->enricher->setLogger(fn($msg) => $enricherLog->overwrite("  └──{$msg}"));
                $summary[] = $this->enricher->process($source, $target, $io);
            } catch (\Exception $e) {
                $io->error("Failed at $target: " . $e->getMessage());
                if (!$io->confirm('Continue with remaining targets?', true)) {
                    break;
                }
                $summary[] = ['target' => $target, 'count' => 0, 'path' => 'N/A', 'status' => '<error>FAIL</error>'];
            }
        }

        $enricherLog->overwrite('');
        $targetLog->overwrite('');

        return $summary;
    }

    /**
     * Renders a table with the results of the pipeline execution.
     * 
     * @param array $summary Data rows containing target, count, path, and status.
     */
    private function renderSummaryTable(array $summary, SymfonyStyle $io): void
    {
        $table = $io->createTable();
        $table->setHeaders(['Target', 'Roads Found', 'Output File', 'Status']);

        foreach ($summary as $row) {
            $table->addRow([
                $row['target'],
                $row['count'],
                $row['path'],
                $row['status']
            ]);
        }

        $totalRoads = array_sum(array_column($summary, 'count'));
        $io->success("Pipeline complete. Total roads processed: $totalRoads");

        $table->render();
    }
}
