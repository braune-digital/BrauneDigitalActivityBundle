# BrauneDigitalActivityBundle

## Installation
Install the bundle using composer:  
```
composer require braune-digital/activity-bundle "~1.1"
```  

And enable the Bundle in your AppKernel:

```php
public function registerBundles()
    {
        $bundles = array(
          ...
          new SimpleThings\EntityAudit\SimpleThingsEntityAuditBundle(),
          new BrauneDigital\ActivityBundle\BrauneDigitalActivityBundle(),
          ...
        );
```
