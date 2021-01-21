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































