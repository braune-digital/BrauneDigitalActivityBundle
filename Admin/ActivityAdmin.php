<?php


namespace BrauneDigital\ActivityBundle\Admin;

use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class ActivityAdmin extends Admin
{
    // Fields to be shown on create/edit forms
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('changedFields', null, array(
                'read_only' => true
            ))
            ->add('changedDate', 'sonata_type_datetime_picker', array('required' => false, 'disabled'  => true))
            ->add('reviewState', null, array(
                'read_only' => true
            ))
            ->add('auditedEntityId', null, array(
                'read_only' => true
            ))
            ->add('baseRevisionId', null, array(
                'read_only' => true
            ))
            ->add('changeRevisionId', null, array(
                'read_only' => true
            ))
            ->add('baseRevisionRevType', null, array(
                'read_only' => true
            ))
            ->add('changeRevisionRevType', null, array(
                'read_only' => true
            ))
        ;
    }


    // Fields to be shown on filter forms
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $reviewChoices = array('unreviewed', 'approved', 'rejected');

        $reviewChoices = array_combine($reviewChoices, $reviewChoices);
        $datagridMapper
            ->add('changedDate', 'doctrine_orm_date')
            ->add('reviewState', 'doctrine_orm_choice', array(), 'choice',  array('choices' => $reviewChoices))
            ->add('user');
    }

    // Fields to be shown on lists
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('changes', 'string', array(
                    'template' => 'BrauneDigitalActivityBundle:Activity:list__changes.html.twig')
            )
            ->add('reviewState', 'string', array(
                    'template' => 'BrauneDigitalActivityBundle:Activity:list__review_state.html.twig')
            )
            ->add('_action', 'actions', array(
                'actions' => array(
                    'changeState' => array(
                        'template' => 'BrauneDigitalActivityBundle:Activity:list__action_change_state.html.twig'
                    )
                )
            ))
        ;
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection
            ->clearExcept(array('list'))
            ->add('changeState', $this->getRouterIdParameter().'/changeState/{state}');
    }

}