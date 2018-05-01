<?php

return [
    'name'        => 'MauticExtendeeToolsBundle',
    'description' => 'Extend your Mautic with awesome features',
    'author'      => 'kuzmany.biz',
    'version'     => '1.0.0',
    'routes'      => [
        'main' => [
            'mautic_plugin_extendee' => [
                'path'       => '/extendee/tools/{objectAction}/{objectId}',
                'controller' => 'MauticExtendeeToolsBundle:ExtendeeTools:execute',
            ],
        ],
    ],
    'services'    => [
        'events' => [
            'mautic.plugin.extendee.button.subscriber' => [
                'class'     => \MauticPlugin\MauticExtendeeToolsBundle\EventListener\ButtonSubscriber::class,
                'arguments' => [
                    'mautic.helper.integration',
                ],
            ],
        ],
        'others' => [
            'mautic.plugin.extendee.helper' => [
                'class' => \MauticPlugin\MauticExtendeeToolsBundle\Helper\ExtendeeToolsHelper::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.helper.integration'
                ],
            ],
        ],
        'integrations' => [
            'mautic.integration.ECronTester' => [
                'class'     => \MauticPlugin\MauticExtendeeToolsBundle\Integration\ECronTesterIntegration::class,
                'arguments' => [
                    'mautic.plugin.extendee.helper',
                ],
            ],
        ],
        'forms'=>[
            'mautic.plugin.extendee.form.type.send_example' => [
                'class' => \MauticPlugin\MauticExtendeeToolsBundle\Form\Type\SendContactsExampleToEmailType::class,
                'arguments' => [
                    'mautic.helper.user',
                ],
                'alias' => 'send_contacts_example_to_email',
            ],
        ]
    ],
];
