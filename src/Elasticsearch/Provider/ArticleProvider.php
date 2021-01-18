<?php

declare(strict_types=1);

// src/Elasticsearch/Provider/ArticleProvider.php

namespace App\Elasticsearch\Provider;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use FOS\ElasticaBundle\Provider\PagerfantaPager;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use Pagerfanta\Doctrine\Collections\CollectionAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

class ArticleProvider implements PagerProviderInterface
{
    private $articleRepository;
    private $translation;

    public function __construct(ArticleRepository $articleRepository, TranslatorInterface $translation)
    {
        $this->articleRepository = $articleRepository;
        $this->translation = $translation;
    }

    /**
     * @param array<mixed> $options
     * @return PagerfantaPager
     */
    public function provide(array $options = [])
    {
        $articles = $this->articleRepository->findActive();
        dd($articles);
        foreach ($articles as $article) {
            $domain = $article->isArticle() ? 'blog' : 'snippet';
            foreach (['En', 'Fr'] as $locale) {
                // keywords
                $fct = 'setKeyword'.$locale;
                $keywords = [];
                foreach (explode(',', $article->getKeyword() ?? '') as $keyword) {
                    $keywords[] = $this->translation->trans($keyword, [], 'breadcrumbs', strtolower($locale));
                }
                $article->$fct(implode(',', $keywords));

                // title
                $fct = 'setTitle'.$locale;
                $article->$fct($this->translation->trans('title_'.$article->getId(), [], $domain, strtolower($locale)));

                // headline
                $fct = 'setHeadline'.$locale;
                $article->$fct($this->translation->trans('headline_'.$article->getId(), [], $domain, strtolower($locale)));

                // There is only for articles to get the full fontent stored in i18n files
                if ($article->isArticle()) {
                    $i18nFile = 'post_'.$article->getId().'.'.strtolower($locale).'.yaml';
                    $file = \dirname(__DIR__, 3).'/translations/blog/'.$i18nFile;
                    $translations = Yaml::parse((string) file_get_contents($file));
                    $translations = array_map('strip_tags', $translations); // tags are useless, only keep texts
                    $translations = array_map('html_entity_decode', $translations);
                    $fct = 'setContent'.$locale;
                    $article->$fct(implode(' ', $translations));
                }
            }
        }

        return new PagerfantaPager(new Pagerfanta(new CollectionAdapter(new ArrayCollection($articles))));
    }
}
