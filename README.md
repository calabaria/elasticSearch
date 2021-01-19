## Implémenter un moteur de recherche avec elasticsearch et Symfony 5

<h4>Prérequis</h4>
<p>Je vais supposer que vous avez au moins une connaissance basique de Symfony4. Que vous savez comment initialiser une application et que vous savez comment gérer un schéma de base de données avec un ORM (nous utiliserons ici Doctrine). Comme un fichier "Docker compose" sera utilisé</p>

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
  
  ```html
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
```
<p>Elasticsearch head va nous permettre de rapidement pouvoir contrôler l'état de notre cluster Elasticsearch local et adminer est une interface basique d'administration de bases de données (comme PhpMyAdmin).</p>
