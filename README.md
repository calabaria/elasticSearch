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

```html
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
test
