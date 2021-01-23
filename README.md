## Implémenter un moteur de recherche avec elasticsearch et Symfony 5

<h4>Prérequis</h4>
<p>Je vais supposer que vous avez au moins une connaissance basique de Symfony5. Que vous savez comment initialiser une application et que vous savez comment gérer un schéma de base de données avec un ORM (nous utiliserons ici Doctrine), un fichier "Docker" sera utilisé aussi.</p>

<h4>Configuration</h4>
PHP 7.4, Symfony 5.2

<h4>Mise en place de l'environnement de développement avec Docker compose</h4>
<p>Tout d'abord, nous devons préparer notre environnement de développement afin de pouvoir travailler (nous amuser ? 😄) dans de bonnes conditions. Voyons comment installer la plupart des composants que nous allons utiliser avec Docker compose. Cet environnement comprendra :</p>

  ```
  * elasticsearch 6.8
  * elastic head 5
  * MySQL 5.7
  * Adminer (last stable)
  ```
  
<p>Elasticsearch head va nous permettre de rapidement pouvoir contrôler l'état de notre cluster Elasticsearch local et adminer est une interface basique d'administration de bases de données (comme PhpMyAdmin).</p>

Jetons un coup d'œil au fichier `docker-compose.yaml` :

```yaml
# ./docker-compose.yaml

# DEV docker compose file ——————————————————————————————————————————————————————
# Check out: https://docs.docker.com/compose/gettingstarted/
version: '3.7'

# docker-compose -f docker-compose.yaml up -d
services:

  # Database ———————————————————————————————————————————————————————————————————

  # MySQL server database (official image)
  # https://docs.docker.com/samples/library/mysql/
  db:
    image: mysql:5.7
    container_name: sb-db
    command: --default-authentication-plugin=mysql_native_password
    ports:
      - "3309:3306"
    environment:
      MYSQL_ROOT_PASSWORD: root

  # adminer database interface (official image)
  # https://hub.docker.com/_/adminer
  adminer:
    container_name: sb-adminer
    depends_on:
      - db
    image: adminer
    ports:
      - "8089:8080"

  # elasticsearch ——————————————————————————————————————————————————————————————

  # elasticsearch server (official image)
  # https://www.elastic.co/guide/en/elasticsearch/reference/current/docker.html
  elasticsearch:
    container_name: sb-elasticsearch
    image: docker.elastic.co/elasticsearch/elasticsearch:6.8.3 # 6.8.4 out
    ports:
      - "9209:9200"
    environment:
      - "discovery.type=single-node"
      - "bootstrap.memory_lock=true"
      - "ES_JAVA_OPTS=-Xms108m -Xmx108m"
      - "xpack.security.enabled=false"
      - "http.cors.enabled=true"
      - "http.cors.allow-origin=*"

  # elasticsearch head manager (fork of mobz/elasticsearch-head for elasticsearch 6)
  # /!\ it isn't an official image /!\
  # https://hub.docker.com/r/tobias74/elasticsearch-head
  elasticsearch-head:
    container_name: sb-elasticsearch-head
    depends_on:
      - elasticsearch
    image: tobias74/elasticsearch-head:6
    ports:
      - "9109:9100"
```
Nous avons deux sections distinctes. La première contient les composants relatifs à Elasticsearch, la seconde étant relative à la base de données. Pour lancer le hub Docker, lancez la commande suivante :

`docker-compose -f docker-compose.yaml up -d`

![docker-compose](https://user-images.githubusercontent.com/16940107/105088910-bdc2f280-5a9c-11eb-8028-86d8cdb1257f.png)

Maintenant, on peut accéder aux composants exposés en HTTP du hub Docker :

* Adminer http://localhost:8089
* elastic head http://localhost:9109/
* elastic http://localhost:9209/

Plusieurs remarques : pour accéder à la base de données avec adminer, on doit spécifier un serveur, pour notre hub, c'est la clé container_name que nous avons paramétré dans le fichier docker-compose.yml. Dans ce cas c'est sb-db, l'utilisateur est "root", de même pour le mot de passe. Ne pas utiliser en production ! ⛔

![adminer](https://user-images.githubusercontent.com/16940107/105091399-15169200-5aa0-11eb-9e6e-06aea3d9a672.png)

Pour ce projet, je démarre le serveur HTTP local avec la commande suivante :

`php -S localhost:8000 -t public`

Alors, on peut accéder au projet localement à l'URL http://localhost:8000. Sur mon MacBookPro, j'ai installé PHP avec Homebrew, sur ma station de travail macOS 10.13.6 (High Sierra), PHP 7.2 était la version installée (les trois configurations fonctionnent sans le moindre problème). Nous ne verrons pas ici comment un installer un environnement PHP complet avec Docker. Maintenant que notre environnement de développement est prêt, voyons comment créer un index de données Elasticsearch.

<h4>Installer et configurer le bundle FOSElastica</h4>

Tout d'abord nous allons installer le bundle FOSElastica (Vous pourriez évidemment utiliser directement elastica ou une autre interface). Veuillez noter que nous n'utiliserons pas la dernière version d'Elasticsearch (7.3) car le bundle ne semble pas encore gérer cette version. Notez aussi que changer la version d'Elasticsearch que l'on utilise est aussi simple que de changer 6.8.10 par 7.10.0 dans le fichier docker compose ! 
C'est l'immense avantage d'utiliser Docker. 💪

`composer require friendsofsymfony/elastica-bundle`

Ajoutez ces deux lignes dans votre fichier `.env` :

```env
ES_HOST=localhost
ES_PORT=9209
```

Donc nous devons récupérer ces deux variables d'environnement dans les paramètres de l'application. Ajoutez les deux lignes suivantes à votre fichier `config/services.yaml` :

```yaml
# config/services.yaml
parameters:
  es_host: '%env(ES_HOST)%'
  es_port: '%env(ES_PORT)%'
```

Enfin, nous pouvons utiliser ces deux nouveaux paramètres dans le fichier de configuration fos_elastica (on pourrait aussi récupérer directement les variables d'environnement avec `%env()%` ) :

```yaml
fos_elastica:
    clients:
        default: { host: '%es_host%', port: '%es_port%' }
```

Ouvrez le fichier `config/packages/fos_elastica.yaml` le contenu de votre fichier doit ressembler à ça :

```yaml
# config/packages/fos_elastica.yaml
fos_elastica:
    clients:
        default: { host: '%es_host%', port: '%es_port%' }
    indexes:
        app: null
```

Maintenant, on peut lancer la création de l'index pour vérifier que notre paramétrage est correct :

`php bin/console fos:elastica:create` 

![elastic-head](https://user-images.githubusercontent.com/16940107/105107596-c1fd0900-5ab8-11eb-8269-4e6b8f6208f4.png)

Maintenant, voyons comment ajouter des données dans l'index. Nous ne verrons pas ici tout le processus de création d'un modèle, des entités et tables correspondantes. j'ai une table article qui contient tous mes articles. Notre prochain objectif va être d'ajouter tous les articles à l'index Elasticsearch.

<h4>Indexation des données dans Elasticsearch</h4>

Dans la suite de cet article, je prendrai mon schéma de base comme référence (si vous avez un autre shéma de base, remplacez donc App\Entity\Article par le nom de votre entité). Même chose au sujet des propriétés de l'entité. Tout d'abord, ajoutons quelques champs dans le mapping Elasticsearch :

```yaml
# Read the documentation: https://github.com/FriendsOfSymfony/FOSElasticaBundle/blob/master/doc/setup.md
fos_elastica:
    clients:
        default: { host: '%es_host%', port: '%es_port%' }
    indexes:
        app:
            types:
                articles:
                    properties:
                        type: ~
                        name: ~
                        slug: ~
                        keyword: ~
                    persistence:
                        driver: orm
                        model: App\Entity\Article
 ```
 
Gardons le paramétrage par défaut et lançons la commande d'indexation qui va nous permettre de rafraîchir les données de l'index

`php bin/console fos:elastica:populate`

![populate](https://user-images.githubusercontent.com/16940107/105187016-96b70000-5b32-11eb-9e97-105487ce66ee.png)

Si vous voyez ceci, c'est que la commande s'est déroulée avec succès. Nous pouvons vérifier que les documents ont bien été indexés. Ouvrez l'interface "Elasticsearch head", cliquez sur l'onglet "naviguer" puis cliquez sur un document pour voir le JSON brut qui lui est attaché. On peut voir l'id de l'entité (14) et les différents champs que nous avons déclaré précédemment (type, name, slug et keyword).

![json-result](https://user-images.githubusercontent.com/16940107/105189974-d501ee80-5b35-11eb-8c23-c6a5aa823bf3.png)

Maintenant que nous avons un index avec quelques données, essayons d'y faire une recherche.

<h4>Rechercher et afficher des résultats</h4>

Par souci de clarté, nous allons créer un contrôleur basique dédié à la recherche. Premièrement, nous devons affecter une variable de liaison (🇬🇧 bind) au service de recherche lié au type articles. Ce service est généré automatiquement par le bundle FOSElastica en fonction des types déclarés dans la configuration. Ajoutez cette ligne au fichier `config/services.yaml`.

```yaml
# config/services.yaml
services:
    _defaults:
        bind:
            $articlesFinder: '@fos_elastica.finder.app.articles'
```

Grâce à l'autoloading, nous pouvons désormais injecter ce service dans notre nouveau contrôleur :

```php
<?php declare(strict_types=1);

// src/Controller/SearchPart1Controller.php

namespace App\Controller;

use Elastica\Util;
use FOS\ElasticaBundle\Finder\TransformedFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;


final class SearchController extends AbstractController
{
    /**
     * @Route("/",  name="search")
     */
    public function search(Request $request, SessionInterface $session, TransformedFinder $articlesFinder): Response
    {
        $q = (string) $request->query->get('q', '');
        $results = !empty($q) ? $articlesFinder->findHybrid(Util::escapeTerm($q)) : [];

        $session->set('q', $q);
        $compacted = compact('results', 'q');
        return $this->render('search/index.html.twig', $compacted);
    }
}
```

L'action de ce contrôleur va être très succincte. Nous récupérons le mot clé à partir d'un paramètre GET (q pour "🇬🇧 query") de la requête HTTP. Ensuite nous appelons la fonction findHybrid pour chercher les articles correspondant, puis nous sauvons le mot-clé en session. Pour chaque résultat, la fonction findHybrid va retourner deux objets : Le premier, le "hit" va contenir les métas informations de la réponse brute d'Elasticsearch. C'est dans cet objet que nous allons récupérer le score du document. Quand on fournit un mot-clé, tous les résultats sont triés par score, du plus au moins pertinent. Le second objet est l'entité Doctrine liée au résultat de recherche. Ainsi, nous n'avons pas à traiter directement la réponse brute Elasticsearch. Maintenant nous pouvons afficher les résultats :

```twig
{% extends 'layout.html.twig' %}

{# templates/search/index.html.twig #}

{% trans_default_domain 'search' %}

{% block content %}
  <!-- START CONTAINER FLUID -->
  <div class=" container-fluid   container-fixed-lg">
      <!-- BEGIN PlACE PAGE CONTENT HERE -->
      <div class="col-md-6">
          <form class="search-job" action="{{ path('search') }}">
              <div class="row no-gutters">
                  <div class="col-md-8">
                      <div class="form-group">
                          <label>Votre recherche</label>
                          <div class="form-field">
                              <div class="icon"><span class="icon-briefcase"></span></div>
                              <input type="text" id="search-field" name="q" class="form-control" autocomplete="off" list="suggest-list" value="{{ app.request.query.get('q') }}" placeholder="Search..." >
                          </div>
                      </div>
                      <datalist id="suggest-list">
                      </datalist>
                  </div>
              </div>
          </form>
      </div>
      <div class="card card-default bg-complete" data-pages="card">
          <div class="card-header ui-sortable-handle">
              <div class="card-title">Searched word "{{ q }}"
              </div>
          </div>
          <div class="card-body">
              <h3 class="text-white">
                  <span class="semi-bold">{{ 'Your search'}}</span> {{ results|length }} {{ 'result'}}.
              </h3>
              <p class="text-white">Suggestions :
              </p>
          </div>
      </div>
      {% for result in results %}
          {% set hit = result.result.hit %}
          {% set article = result.transformed %}
          <div class="col-md-12">
              <div class="card-body no-padding">
                  <div id="card-circular-minimal" class="card card-default">
                      <div class="card-header  ">
                          <div class="card-title">
                              {{ 'score'|trans }} <b>{{ hit._score }}</b>
                          </div>
                      </div>
                      <div class="card-body">
                          <h3> <span class="semi-bold">Type : </span> {{ (article.type)|trans({}, 'blog') }} </h3>
                          <p> Name :{{ article.name }} </p>
                      </div>
                  </div>
              </div>
          </div>
      {% endfor %}
      <div class="col-md-12">
          {% if results is empty %}
              <p class="h3">{{ 'no_results'|trans }}</p>
          {% endif %}
      </div>
      <!-- END PLACE PAGE CONTENT HERE -->
  </div>
  <!-- END CONTAINER FLUID -->
{% endblock %}
```

Regardons le template, les deux lignes les plus importantes sont au tout début de la boucle `for` :

```twig
{% set hit = result.result.hit %}
{% set article = result.transformed %}
```

Comme indiqué auparavant, tout d'abord nous récupérons l'objet "hit" par lequel nous pouvons récupérer le score avec hit._score. (il est affiché sur la liste de résultats à droite du titre de l'article ou du snippet). Ensuite, nous récupérons l'entité Doctrine Article avec result.transformed. Maintenant, nous pouvons accéder aux getters comme nous avons l'habitude de le faire avec Twig. Par exemple, article.isArticle va retourner vrai si l'article est un article de blog et faux si c'est un snippet. (il y a uniquement deux types d'article). Et voilà ! Vous pouvez tester la recherche avec le formulaire généré :

![search-form](https://user-images.githubusercontent.com/16940107/105356487-c0842b80-5bf3-11eb-80d7-4ca53f93c816.png)

<h4>Utilisation d'un alias Elasticsearch</h4>

Jusqu'à maintenant nous utilisions directement l'index principal pour ajouter des données. Mais que se passe t-il si le mapping change ? Si des champs sont ajoutés ou supprimés ? Ça pourrait être dangereux... Utiliser un alias nous permet d'éviter des périodes d'indisponibilité comme la bascule des index est faite uniquement quand toutes les données ont été indexées. C'est particulièrement vrai si vous avez un grand volume de données et que l'indexation prend un temps conséquent. Tout d'abord supprimons l'index "app" qui existe. On peut le faire avec une commande cURL (on peut aussi utiliser le plugin head : actions -> effacer...) :

`curl -i -X DELETE 'http://localhost:9209/app'` comme réultat de la commande on aura le message suivant `{"acknowledged":true}` 

Ajouter l'option `"use_alias: true"` dans la configuration fos_elastica :

```yaml
# config/packages/fos_elastica.yaml
fos_elastica:
    clients:
        default: { host: '%es_host%', port: '%es_port%' }
    indexes:
        app:
            use_alias: true
            types:
                articles:
                    # ...
```

Maintenant, lançons la commande `fos:elastica:populate`. Cette fois nous pouvons voir que l'index créé ne porte plus le nom "app" mais un suffixe de date a été ajouté. De plus un alias a été automatiquement ajouté à l'index, c'est cet alias qui porte le nom "app" désormais. À ce point, votre cluster Elasticsearch devrait ressembler à ça :

![Capture d’écran 2021-01-21 à 15 26 25](https://user-images.githubusercontent.com/16940107/105364189-29bc6c80-5bfd-11eb-8f2f-4f118a9f87c5.png)

Lancez de nouveau la commande populate, mais cette fois avec l'option `--no-delete`. On voit qu'il y a deux index mais l'alias pointe désormais sur le plus récent. La gestion de l'alias est automatiquement prise en charge par le bundle, on n'a donc pas à le faire manuellement. L'index le plus vieux a été "fermé". ça veut dire que les données sont toujours présentes mais on ne peut plus y accéder, les opérations de lecture / écriture sont bloquées. Le cluster ressemble désormais à cela :

![Capture d’écran 2021-01-21 à 15 28 40](https://user-images.githubusercontent.com/16940107/105364422-6ee09e80-5bfd-11eb-8ef8-c7baa1bd37f6.png)

Imaginez que vous ayez un bug critique et que le nouvel index en soit la cause. On pourrait "ouvrir" l'ancien index, supprimer l'alias en cours et l'assigner à l'ancien index afin de faire fonctionner l'application à nouveau. C'est tout pour la partie alias. Maintenant voyons comment créer un fournisseur de données personnalisé pour indexer des données.

<h4>Création d'un fournisseur de données personnalisé</h4>

Voyons comment indexer ces textes pour rendre la recherche bien plus pertinente. Nous avons besoin de créer un fournisseur d'accès personnalisé. Ce service aura besoin d'accéder à la base de données avec l'ORM. Voici le code :

```php
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
```

> [“Améliorer la pertinence est difficile, vraiment difficile.”](http://www.siteduzero.com) — Le blog Elasticsearch

<h4>Installation de Kibana</h4>

Tout d'abord nous allons améliorer notre stack Elasticsearch. Jusqu'à maintenant, nous avons utilisé le plugin "head" pour gérer notre cluster. Mais cet outil de développement est assez ancien et n'est plus maintenu. Donc, ajoutons Kibana à notre hub docker. Kibana est un plugin open-source de visualisation de données pour Elasticsearch. Bien sûr, il permet aussi de faire les tâches de maintenance courantes que nous avions l'habitude de faire avec head : supprimer, fermer un index, créer et supprimer un alias, vérifier un document, vérifier le mapping des index, mais il permet bien plus encore ! La liste de ce qu'il est possible de faire est assez impressionnante (regardez le menu à gauche de la capture d'écran suivante). Ajoutons l'entrée correspondante dans le fichier `docker-compose.yaml` :

```yaml
kibana:
    container_name: sb-kibana
    image: docker.elastic.co/kibana/kibana:6.8.10
    ports:
      - "5601:5601"
    environment:
      - "ELASTICSEARCH_URL=http://sb-elasticsearch"
    depends_on:
      - elasticsearch
```

Comme vous pouvez le voir, nous passons l'URL du serveur Elasticsearch dont le nom d'hôte est celui du conteneur docker (sb-elasticsearch). Nous gardons le port standard 5601. Nous utilisons aussi la même version d'image (6.8.10) que nous avons utilisée pour Elasticsearch afin qu'il n'y ait pas de problème de compatibilité. Si vous redémarrez le hub docker, vous pouvez accéder à [la page de gestion des index](http://localhost:5601/app/kibana#/management/elasticsearch/index_management/indices?_g=())

![kibana](https://user-images.githubusercontent.com/16940107/105504810-7de05300-5cc8-11eb-9c01-6304a6403979.png)

Voilà pour Kibana. Je vais m'arrêter ici pour cette partie, ça demanderait bien plus qu'un article pour présenter toutes les fonctionnalités. Accédez [au site officiel](https://www.elastic.co/fr/kibana) pour plus d'informations. Kibana est très puissant, il peut aussi être utilisé pour consulter vos logs Symfony ! À ce sujet, je vous conseille la lecture de [ce très intéressant article du blog JoliCode](https://jolicode.com/blog/how-to-visualize-symfony-logs-in-dev-with-elasticsearch-and-kibana).

<h4>Ajout d'un autocomplete dans la barre de recherche</h4>

Comme vous pouvez le voir, j'ai mis un champ de recherche dans l'entête de ce site. Ça marche, mais si nous essayions de compléter la saisie de l'utilisateur afin de lui suggérer des termes qu'il peut trouver sur ce blog ? Voyons comment nous pouvons faire cela avec Elasticsearch, nous allons construire un index qui sera dédié à cette fonctionnalité.

<h5>Configuration du mapping<h5>
  
```yaml
fos_elastica:
    clients:
        default: { host: '%es_host%', port: '%es_port%' }
    indexes:
        app:
          ###
        suggest:
            use_alias: true
            settings:
                index:
                    analysis:
                        analyzer:
                            suggest_analyzer:
                                type: custom
                                tokenizer: standard
                                filter: [lowercase, asciifolding]
            types:
                keyword:
                    properties:
                        locale:
                            type: keyword
                        suggest:
                            type: completion
                            analyzer: suggest_analyzer
                            contexts:
                                - name: locale
                                  type: category
```

Quelques explications à propos de cet index et de son mapping. Avant de déclarer le type, j'ajoute un analyseur dans la section "setting". Le filtre `asciifolding` va nous permettre d'ignorer les accents pour permettre à la suggestion de fonctionner même si ceux-ci ne sont pas utilisés. Par exemple, si on saisit "element", le mot "élément" devrait être suggéré.
Ensuite, dans la section "type", on utilise aussi un alias tout comme l'index "app". Dans le mapping nous avons deux propriétés : suggest qui est de type "completion". Nous avons besoin de ce type particulier pour utiliser le "suggester" Elasticsearch comme nous le verrons. 
Si nous relançons la commande d'indexation, le nouvel index est créé.

![index](https://user-images.githubusercontent.com/16940107/105507881-03b1cd80-5ccc-11eb-87a7-e3d97f4f2a5e.png)

<h4>Peupler l'index de suggestion</h4>

Maintenant, nous allons peupler le nouvel index de suggestion. Comme il n'y a pas de modèle Doctrine associé, nous n'allons pas créer un fournisseur de données mais une commande Symfony. L'idée est d'extraire tous les mots qui ont été utilisés dans l'index app. Voilà la nouvelle commande Symfony : (quelques éclaircissements après le code 🤔)

```php
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
```

Voici la sortie console de la nouvelle tâche "populate" du MakeFile :

![make](https://user-images.githubusercontent.com/16940107/105615487-6d67cf80-5dd1-11eb-9436-54bd992f47f0.png)

Le contenu de cette nouvelle entrée :

![reset](https://user-images.githubusercontent.com/16940107/105615543-cd5e7600-5dd1-11eb-88b9-c8d016662600.png)

Vous pouvez trouver le [MakeFile Symfony complet dans ce snippet](https://www.strangebuzz.com/fr/snippets/le-makefile-parfait-pour-symfony) moi j'ai utilisé que les lignes dont j'ai besoin. Maintenant que l'index est peuplé, voyons comment l'utiliser pour l'implémentation de la fonctionnalité autocomplete.

<h4>Implémentation de l'autocomplete</h4>

Le but ici va être d'ajouter une action qui va retourner via Ajax les suggestions pour le widget autocomplete alors que l'utilisateur saisit un mot-clé. Créons un nouveau contrôleur dédié à cette tâche :

```php
<?php

declare(strict_types=1);

// src/Controller/SuggestController.php

namespace App\Controller;

use App\Elasticsearch\ElastiCoil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


final class SuggestController extends AbstractController
{
    /**
     * @Route("/suggest", name="suggest")
     */
    public function suggest(Request $request, ElastiCoil $elastiCoil): JsonResponse
    {
        $q = (string) $request->query->get('q', '');

        return $this->json($elastiCoil->getSuggestions($q));
    }
}
```

Et le service Elasticsearch personnalisé :

```php
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
```
     
Quelques explications : 💡

*Comme l'action de recherche, nous récupérons la saisie de l'utilisateur par le paramètre GET "q".
*Ensuite nous créons un objet elastica Suggest avec le nom de la propriété du mapping à utiliser.
*Juste en dessous, on ajoute un contexte qui va nous permettre de filtrer les mots retournés : dans ce cas on filtre selon la langue de la page en cours (en ou fr).
*Ensuite, on extrait les options retournées par la réponse Elasticsearch.
*Finalement, nous retournons une réponse de type JSON (JsonResponse) contenant un tableau simple avec les options à afficher à l'utilisateur.



















