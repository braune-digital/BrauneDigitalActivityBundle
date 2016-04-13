<?php

namespace BrauneDigital\ActivityBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BrauneDigitalActivityExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('braune_digital_activity_observed_classes', $config['observed_classes']);
        $container->setParameter('braune_digital_activity_doctrine_subscribing', $config['doctrine_subscribing']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * @param ContainerBuilder $container
     * Add UserInterface to Doctrine ORM Configuration
     */
    public function prepend(ContainerBuilder $container) {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        $classes = array_keys($config['observed_classes']);
        if ($classes) {
            $simpleAudit = call_user_func_array('array_replace_recursive', $container->getExtensionConfig('simple_things_entity_audit'));
            $base = array();
            if(array_key_exists('audited_entities', $simpleAudit)) {
                $base = $simpleAudit['audited_entities'];
            }

            $simpleAudit['audited_entities'] = array_unique(array_merge($base, $classes));

            $container->prependExtensionConfig('simple_things_entity_audit', $simpleAudit);
        }
    }
}
