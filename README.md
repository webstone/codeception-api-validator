# codeception-api-validator
Validate API Requests and Responses against Swagger / OpenAPI 3.0 definitions

## Installation

You need to add the repository into your composer.json file

```bash
    composer require --dev awuttig/codeception-api-validator
```


## Usage

You can use this module as any other Codeception module, by adding 'ApiValidator' to the enabled modules in your Codeception suite configurations.

### Enable module and setup the configuration variables

- The `schema` should be set in config file directly.

```yml
modules:
    enabled:
        - ApiValidator:
            depends: [REST, PhpBrowser]
            schema: '/tests/_data/swagger.yaml'
            
 ```  

Update Codeception build
  
```bash
  codecept build
```

### Implement the cept / cest 

```php
  $I->wantToTest('Validate request and response against OpenAPI Specification.');
  
  $I->sendGet('api/foo/bar');
  
  $I->seeRequestIsValid();
  $I->seeResponseIsValid(); 
```

### Methods

#### seeRequestIsValid()

Validates the current request against the current schema definiton.

```php
  $I->seeRequestIsValid();
  
```

#### seeResponseIsValid()

Validates the current response against the current schema definiton.

```php
  $I->seeResponseIsValid();
  
```

#### seeRequestAndResponseAreValid()

Validates the current request and response against the current schema definiton.

```php
  $I->seeRequestAndResponseAreValid();
  
```

## Authors

![](https://avatars0.githubusercontent.com/u/726519?s=40&v=4)

* **André Wuttig** - *Concept, Initial work* - [aWuttig](https://github.com/aWuttig)

See also the list of [contributors](https://github.com/portrino/codeception-api-validator/graphs/contributors) who participated in this project.
