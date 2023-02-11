Filter logic for API Platform
=============================
Combines API Platform ORM Filters with AND, OR and NOT according to client request.
- supports nested logic (like parentheses in SQL)
- supports multiple criteria for the same property
- existing requests keep working unmodified if not using "and", "or" or "not" as query parameters.
- works with built in filters of Api Platform, except for DateFilter
  with EXCLUDE_NULL. A DateFilter subclass is provided to correct this.
- For security reasons as of version 2.0 criteria from extensions and filters that
  are not nested in "and", "or" or "not" are allways combined through AND with the criteria  
  added by LogicFilter

Usage
-----
Once the FilterLogic class and service configuration have been installed in you app,
just add it as the last ApiFilter annotation. Then make a request with filter parameters nested in and/or in the query string.

For example if you have:
```php8
#[ApiResource]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact', 'price' => 'exact', 'description' => 'partial'])]
#[ApiFilter(FilterLogic::class)]
class Offer {
// ...
}
```
Callling the collection get operation with:
```uri
/offers/?or[price]=10&or[description]=shirt
```
will return all offers with a price being exactly 10 OR a description containing the word "shirt".

NOT expressions are combined like the other expressions trough the compound logic (and, or) they are nested in:  
```uri
/offers/?or[not][price]=10&or[not][description]=shirt 
```
will return (all offers with a price not being 10) OR (a description NOT containing the word "shirt").
If they are not nested in compound logic AND is used:

You can have nested logic and multiple criteria for the same property like this:
```uri
/offers/?and[price]=10&and[][description]=shirt&and[][description]=cotton
```
The api will return all offers with a price being exactly 10 AND 
(a description containing the word "shirt" AND the word "cotton").

Expressions that are nested in "and", "or" or "not" are allways combined with normal 
expressions by AND. For example:
```uri
/offers/?price=10&not[description]=shirt
```
will return all offers with a price being exactly 10 AND a description NOT containing the word "shirt".

```uri
/offers/?price=10&or[][description]=shirt&or[][description]=cotton
```
will return all offers with a price being exactly 10 AND
(a description containing the word "shirt" OR the word "cotton").
So this is the same as:
```uri
/offers/?and[price]=10&and[or][][description]=shirt&and[or][][description]=cotton
```
This may be counterintuitive but it is necessary because the querybuilder may also contain
expressons from extensions that limit access to the data for security and if those
are combined through OR they can be bypassed by the client.

If you want them to be combined by or, move them to be nested in "or":
```uri
/offers/?or[price]=10&or[][description]=shirt&or[][description]=cotton
```
The api will then return all offers with a price being exactly 10 
OR a description containing the word "shirt" 
OR a description containing the word "cotton".


You can in/exclude filters by class name by configuring classExp. For example:
```php docblock
* @ApiFilter(FilterLogic::class, arguments={"classExp"="/ApiPlatform\\Core\\Bridge\\Doctrine\\Orm\\Filter\\+/"})
```
will only apply API Platform ORM Filters in logic context.

Installation
------------
This version is for Api Platform 3.0 and 2.7 with metadata_backward_compatibility_layer set to false
```shell
composer require metaclass-nl/filter-bundle "^3.0"
```

Then add the bundle to your api config/bundles.php:
```php
    // (...)
    Metaclass\FilterBundle\MetaclassFilterBundle::class => ['all' => true],
];
```

Nested properties workaround
----------------------------

The built-in filters of Api Platform normally generate INNER JOINs. As a result
combining them with OR may not produce results as expected for properties
nested over nullable and to many associations, , see [this issue](https://github.com/metaclass-nl/filter-bundle/issues/2).

As a workaround FilterLogic can convert all inner joins into left joins:
```php8
#[ApiResource]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial', 'keywords.word' => 'exact'])]
#[ApiFilter(FilterLogic::class, arguments: ['innerJoinsLeft' => true])]
class Article {
// ...
}
```
<b>SECURITY WARNING</b>: do not use this option if the working of any of the extionsions 
you use relies on INNER JOINS selecting only rows with NOT NULL values!

In case you do not like FilterLogic messing with the joins you can make
the built-in filters of Api Platform generate left joins themselves by first adding
a left join and removing it later:
```php8
#[ApiResource]
#[ApiFilter(AddFakeLeftJoin::class)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial', 'keywords.word' => 'exact'])]
#[ApiFilter(FilterLogic::class)]
#[ApiFilter(RemoveFakeLeftJoin::class)]
class Article {
// ...
}
```
<b>SECURITY WARNING</b>: Extensions that use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryBuilderHelper::addJoinOnce
or ApiPlatform\Core\Bridge\Doctrine\Orm\PropertyHelperTrait::addJoinsForNestedProperty
may not have been intended to generate left joins instead of inner joins. Of course this would
technically be their error, not yours, but still it is better to prevent an eventual breach of security
then having to deal with the consequences.


With one fo these workarounds the following will find Articles whose title contains 'pro'
as well as those whose keywords contain one whose word is 'php'.
```uri
/articles/?or[title]=pro&or[keywords.word]=php
```
Without a workaround Articles without any keywords will not be found,
even if their titles contain 'pro'.

Both workarounds do change the behavior of ExistsFilter =false with nested properties.
Normally this filter only finds entities that reference at least one entity
whose nested property contains NULL, but with left joins it will also find entities
whose reference itself is empty or NULL. This does break backward compatibility.
This can be solved by extending ExistsFilter, but that is not included
in this Bundle because IMHO the old behavior is not like one would expect given
the semantics of "exists" and therefore should be considered a bug unless it is
documented explicitly to be intentional.

Limitations
-----------
Combining filters through OR and nested logic may be a harder task for your
database and require different indexes. Except for small tables performance
testing and analysis is advisable prior to deployment.  

The built in filters of Api Platform IMHO contain a bug with respect to the JOINs 
they generate. As a result, combining them with OR does not work as expected with properties
nested over to-many and nullable associations. Workarounds are provided, but they
do change the behavior of ExistsFilter =false.

Assumes that filters create <b>semantically complete expressions</b> in the sense that
expressions added to the QueryBuilder through ::andWhere or ::orWhere do not depend
on one another so that the intended logic is not compromised if they are recombined
with the others by either Doctrine\ORM\Query\Expr\Andx or Doctrine\ORM\Query\Expr\Orx.

May Fail if a filter or extension uses QueryBuilder::where or ::add. 

You are advised to check the code of all custom and third party Filters and
not to combine those that use QueryBuilder::where or ::add with FilterLogic
or that produce complex logic that is not semantically complete. For an
example of semantically complete and incomplete expressions see [DateFilterTest](./tests/Filter/DateFilterTest.php).

Be aware that new features added to existing filters (From API Platform or third parties)
may make them create semantically incomplete expressions or use ::where or ::add giving <b>unexpected results
or incorrect SQL when combined through FilterLogic</b>. Semantic versioning only requires minor new versions
for new features, so <b>given the composer.json of this package composer will install them unless you
limit those packages to the minor versions you have tested with</b>.

Credits and License
-------------------
Copyright (c) [MetaClass](https://www.metaclass.nl/), Groningen, 2021. [MetaClass](https://www.metaclass.nl/) offers software development and support in the Netherlands for Symfony, API Platform, React.js and Next.js

[MIT License](./LICENSE).

[API Platform](https://api-platform.com/) is a product of [Les-Tilleuls.coop](https://les-tilleuls.coop)
created by [Kévin Dunglas](https://dunglas.fr).

