Filter logic for API Platform
=============================
Combines existing API Platform ORM Filters with AND and OR according to client request.
- supports nested logic (parentheses)
- supports multiple criteria for the same property
- existing requests keep working unmodified if not using "and" or "or" as query parameters

Branch query-expression-generator
---------------------------------
This branch uses the interface QueryExpressionGeneratorInterface from the
[proof of concept to make existing filters composable](https://github.com/metaclass-nl/core/tree/query-expression-generator).
It only uses filters that declare to implement this interface and ignores the others.
A version that works without this interface but has an extra limitation with respect to 
custom filters is available in [this branch](https://github.com/metaclass-nl/filter-bundle/).

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

You can have nested logic and multiple criteria for the same property like this:
```uri
/offers/?and[price]=10&and[or][][description]=shirt&and[or][][description]=cotton
```
The api will return all offers with a price being exactly 10 AND (a description containing the word "shirt" OR the word "cotton").
Because of the nesting of or the criteria for the description are combined together through
AND with the criterium for price, which must allways be true while only one of the
criteria for the desciption needs to be true for an order to be returned.

Installation
------------
Add the following to the "require" section of your composer.json:
```json
    "metaclass-nl/filter-bundle": "dev-query-expression-generator",
```
and run composer update.

Then add the bundle to your api config/bundles.php:
```php
    // (...)
    Metaclass\FilterBundle\MetaclassFilterBundle::class => ['all' => true],
];
```

Limitations
-----------
Works with built in filters of Api Platform, except for DateFilter with EXCLUDE_NULL.
A DateFilter subclass is provided to correct this.

Assumes that filters create semantically complete expressions in the sense that
expressions added to the QueryBundle through ::andWhere or ::orWhere do not depend
on one another so that the intended logic is not compromised if they are recombined
with the others by either Doctrine\ORM\Query\Expr\Andx or Doctrine\ORM\Query\Expr\Orx.

You are advised to check the code of all custom and third party Filters and
not to combine those that produce complex logic that is not semantically complete. 
For an example of semantically complete and incomplete expressions 
see [DateFilterTest](./tests/Filter/DateFilterTest.php).

You can in/exclude filters by class name by configuring classExp. For example:
```php docblock
* @ApiFilter(FilterLogic::class, arguments={"classExp"="/ApiPlatform\\Core\\Bridge\\Doctrine\\Orm\\Filter\\+/"})
```
will only apply API Platform ORM Filters in logic context.

Credits and License
-------------------
Copyright (c) [MetaClass](https://www.metaclass.nl/), Groningen, 2021.

[MIT License](./LICENSE).

[API Platform](https://api-platform.com/) is a product of [Les-Tilleuls.coop](https://les-tilleuls.coop)
created by [Kévin Dunglas](https://dunglas.fr).