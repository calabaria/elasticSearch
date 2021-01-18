<?php

declare(strict_types=1);

// src/Command/PopulateSuggestCommand.php (used by templates/blog/posts/_51.html.twig)

namespace App\Command;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\NoopWordInflector;
use Elastica\Document;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Finder\TransformedFinder;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Symfony\Component\String\u;

/**
 * Populate the suggest elasticsearch index.
 */
final class PopulateSuggestCommand extends Command
{
    public const NAMESPACE = 'symfony-elastic';
    public const CMD = 'populate';
    public const DESC = 'Populate the "suggest" Elasticsearch index';

    private $articlesFinder;
    private $suggestIndex;
    private $inflector;

    public function __construct(TransformedFinder $articlesFinder, Index $suggestIndex)
    {
        parent::__construct();
        $this->articlesFinder = $articlesFinder;
        $this->suggestIndex = $suggestIndex;
        $this->inflector = new Inflector(new NoopWordInflector(), new NoopWordInflector());
    }

    protected function configure(): void
    {
        [$namespace, $cmd, $desc] = [self::NAMESPACE, self::CMD, self::DESC];
        $this->setName($namespace.':'.$cmd)
            ->setDescription(self::DESC)
            ->setHelp(
                <<<EOT
{$desc}
<info>%command.full_name%</info>
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(self::DESC);
        $pagination = $this->findHybridPaginated($this->articlesFinder, '');
        $nbPages = $pagination->getNbPages();
        $keywords = [];

        foreach (range(1, $nbPages) as $page) {
            $pagination->setCurrentPage($page);
            foreach ($pagination->getCurrentPageResults() as $result) {
                if ($result instanceof HybridResult) {
                    foreach ($result->getResult()->getSource() as $property => $text) {
                        if ($property === 'type') {
                            continue;
                        }
                        $text = strip_tags($text ?? '');
                        $words = str_word_count($text, 2, 'çéâêîïôûàèùœÇÉÂÊÎÏÔÛÀÈÙŒ'); // FGS dot not remove french accents! 🙃
                        $textArray = array_filter($words);
                        $keywords = array_merge($keywords ?? [], $textArray);
                    }
                }
            }
        }

            // Index by locale

            // Remove small words and remaining craps (emojis) 😖
            $keywords = array_unique(array_map('mb_strtolower', $keywords));
            $keywords = array_filter($keywords, static function ($v) {
                return u((string) $v)->length() > 2;
            });
            $documents = [];
            foreach ($keywords as $idx => $keyword) {
                $documents[] = (new Document())
                    ->setType('keyword')
                    ->set('suggest', $keyword);
            }
            $responseSet = $this->suggestIndex->addDocuments($documents);

            $output->writeln(sprintf(' -> TODO: %d -> DONE: <info>%d</info>, keywords indexed.', count($documents), $responseSet->count()));


        return 0;
    }

    /**
     * @return Pagerfanta<mixed>
     */
    private function findHybridPaginated(TransformedFinder $articlesFinder, string $query): Pagerfanta
    {
        $paginatorAdapter = $articlesFinder->createHybridPaginatorAdapter($query);

        return new Pagerfanta(new FantaPaginatorAdapter($paginatorAdapter));
    }
}