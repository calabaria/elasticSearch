## Implémenter un moteur de recherche avec elasticsearch et Symfony 5

![Map-Marker-Marker](https://user-images.githubusercontent.com/16940107/105041268-107fb880-5a63-11eb-860f-b678d00489bc.png) Prérequis

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
