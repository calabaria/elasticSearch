<?php


declare(strict_types=1);

// src/Elasticsearch/ElastiCoil.php

namespace App\Elasticsearch;

use Elastica\Query;
use Elastica\Suggest;
use Elastica\Suggest\Completion;
use Elastica\Util;
use FOS\ElasticaBundle\Elastica\Index;

final class ElastiCoil
{
    public const SUGGEST_NAME = 'completion';
    public const SUGGEST_FIELD = 'suggest';

    private $suggestIndex;

    public function __construct(Index $suggestIndex)
    {
        $this->suggestIndex = $suggestIndex;
    }

    /**
     * Get the a suggest object for a keyword and locale.
     */
    public function getSuggest(string $q): Suggest
    {
        $completionSuggest = (new Completion(self::SUGGEST_NAME, self::SUGGEST_FIELD))
            ->setPrefix(Util::escapeTerm($q))
            ->setSize(5);

        return new Suggest($completionSuggest);
    }

    /**
     * Return suggestions for a keyword and locale as a simple array.
     *
     * @return array<string>
     */
    public function getSuggestions(string $q): array
    {
        $suggest = $this->getSuggest($q);
        $query = (new Query())->setSuggest($suggest);
        $suggests = $this->suggestIndex->search($query)->getSuggests();

        return $suggests[self::SUGGEST_NAME][0]['options'] ?? [];
    }
}