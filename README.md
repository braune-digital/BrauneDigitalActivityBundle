# BrauneDigitalActivityBundle
This Bundle allows the creation of activities based on Entity-Audits.
It also displays the activity in SonataAdmin.

## Requirements
Required:  
* SimpleThingsEntityAuditBundle
* DoctrineORM
  
Optional:
* SonataAdminBundle  
  
## Installation
Install the bundle using composer:  
```
composer require braune-digital/activity-bundle "~1.2"
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
## Configuration
```yaml
braune_digital_activity:
    doctrine_subscribing: true  #enable the direct creation of activities
    observed_classes:           #array of classes that need to be watched
        'AppBundle\Entity\TimedTask': #classname
            fields:                           #watched fields
              created: ~
              title: ~
              modified: ~
        'Application\Ekas\AppBundle\Entity\Step': ~ # watch creation / deletion only
        'AppBundle\Entity\TimedTask':
            fields:
                done: ~
                title: ~
                description: ~
```

## Configure Entities
Resolve UserInterface:  
```yaml
doctrine:
    orm:
        resolve_target_entities:
            BrauneDigital\ActivityBundle\Model\UserInterface: Application\AppBundle\Entity\User
```  
  
  Add Doctrine Relations to your User
```php
  oneToMany:
      activities:
          targetEntity: 'BrauneDigital\ActivityBundle\Entity\Stream\Activity'
          mappedBy: user
          cascade: ["persist", "remove"]
```

## Build a Stream using the consle
```bash
php app/console braunedigital:activity:buildstream
```

## TODO
* Add Usage-Section in Readme
