Filter logic for API Platform
=============================
Combines existing API Platform ORM Filters with AND and OR according to client request.
- supports nested logic (parentheses)
- supports multiple criteria for the same property
- existing requests keep working unmodified if not using "and" or "or" as query parameters

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
Add the class [FilterLogic.php](#file-filterlogic-php) to your api src/Filter folder (create this folder if you don't have one yet)

Add the following service configuration to your api config/services.yml:
```yaml
    'App\Filter\FilterLogic':
        class: 'App\Filter\FilterLogic'
        arguments:
            - '@api_platform.metadata.resource.metadata_factory'
            - '@api_platform.filter_locator'
        public: false
        abstract: true
        autoconfigure: false
```

Limitations
-----------
The normal filtering will still work as usual: filters decide how to apply
themselves to the QueryBuilder. If all use ::andWhere, like the the
built in filters of Api Platform, the order of the ApiFilter attribute/annotation
does not matter, but if some use other methods a different order may yield different
results. FilterLogic uses orWhere for "or" so the order matters. 
If it is the last filter its logic expressions will become the topmost ones, 
therefore defining the primary logic.

Works with built in filters of Api Platform that all use QueryBuilder::andWhere.
May fail in (the rare) case that a custom filter uses QueryBuilder::where or ::add.
You are advised to check the code of all custom and third party Filters and
not to combine those that use QueryBuilder::where or ::add with FilterLogic.
You can in/exclude filters by class name by configuring regExp. For example:
```php docblock
* @ApiFilter(FilterLogic::class, arguments={"regExp"="/ApiPlatform\\Core\\Bridge\\Doctrine\\Orm\\Filter\\+/"})
```
will only apply api platform orm filters in logic context.

Credits and License
-------------------
Copyright (c) [MetaClass](https://www.metaclass.nl/), Groningen, 2021.

[MIT License](#file-license).

[API Platform](https://api-platform.com/) is a product of [Les-Tilleuls.coop](https://les-tilleuls.coop)
created by [Kévin Dunglas](https://dunglas.fr).