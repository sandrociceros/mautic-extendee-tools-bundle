<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeToolsBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Exception\EmailCouldNotBeSentException;
use Mautic\LeadBundle\MauticLeadBundle;
use Mautic\LeadBundle\Model\LeadModel;

class ExtendeeToolsController extends FormController
{


    /**
     * Generates example form and action.
     *
     * @param $leadId
     * @param $objectId
     *
     * @return array|JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function sendContactsExampleToEmailAction($objectId)
    {
        /** @var LeadModel $leadModel */
        $leadModel = $this->getModel('lead');
        /** @var \Mautic\EmailBundle\Model\EmailModel $emailModel */
        $emailModel = $this->getModel('email');
        $email      = $emailModel->getEntity($objectId);

        $cookieVar = md5('mautic.plugin.extendee.send.example.data');

        //set the return URL
        $returnUrl = $this->generateUrl('mautic_email_action', ['objectId' => $objectId, 'objectAction' => 'view']);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['objectId' => $objectId, 'objectAction' => 'view'],
            'contentTemplate' => 'MauticEmailBundle:Email:action',
            'passthroughVars' => [
                'activeLink'    => '#mautic_email_action',
                'mauticContent' => 'email',
            ],
        ];

        if ($email === null) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.lead.lead.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        }

        //do some default filtering
        $savedData = unserialize($this->request->cookies->get($cookieVar));
        $search    = $this->request->get('search', !empty($savedData['search']) ? $savedData['search'] : '');
        $this->get('mautic.helper.cookie')->setCookie(
            $cookieVar,
            serialize(array_merge($savedData, ['search' => $search])),
            3600 * 24 * 31
        );
        $leads = [];

        if (!empty($search)) {
            $filter = [
                'string' => $search,
            ];

            $leads = $leadModel->getEntities(
                [
                    'limit'          => 25,
                    'filter'         => $filter,
                    'orderBy'        => 'l.firstname,l.lastname,l.company,l.email',
                    'orderByDir'     => 'ASC',
                    'withTotalCount' => false,
                ]
            );
        }
        $leadChoices = [];
        foreach ($leads as $l) {
            $leadChoices[$l->getId()] = $l->getPrimaryIdentifier();
        }

        $action = $this->generateUrl(
            'mautic_plugin_extendee',
            ['objectAction' => 'sendContactsExampleToEmail', 'objectId' => $email->getId()]
        );

        $form = $this->get('form.factory')->create(
            'send_contacts_example_to_email',
            [],
            [
                'action' => $action,
                'leads'  => $leadChoices,
                'saved'  => unserialize($this->request->cookies->get($cookieVar)),
            ]
        );

        if ($this->request->getMethod() == 'POST') {
            $valid = true;
            if (!$this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    $data      = $form->getData();
                    $secLeadId = $data['lead_to_example'];
                    $secLead   = $leadModel->getEntity($secLeadId);

                    if ($secLead === null) {
                        return $this->postActionRedirect(
                            array_merge(
                                $postActionVars,
                                [
                                    'flashes' => [
                                        [
                                            'type'    => 'error',
                                            'msg'     => 'mautic.lead.lead.error.notfound',
                                            'msgVars' => ['%id%' => $secLead->getId()],
                                        ],
                                    ],
                                ]
                            )
                        );
                    } elseif ($emailModel->isLocked($email)) {
                        //deny access if the entity is locked
                        return $this->isLocked($postActionVars, $secLead, 'lead');
                    } elseif ($emailModel->isLocked($secLead)) {
                        //deny access if the entity is locked
                        return $this->isLocked($postActionVars, $secLead, 'lead');
                    }

                    $data = array_merge($data, ['search' => $search]);
                    $config = ['to'=>implode(',',$data['emails']['list']), 'useremail'=>['email'=>$objectId]];
                    $this->get('mautic.helper.cookie')->setCookie($cookieVar, serialize($data), 3600 * 24 * 31);
                    try {
                        $this->get('mautic.email.model.send_email_to_user')->sendEmailToUsers($config, $secLead);
                    } catch (EmailCouldNotBeSentException $e) {
                        $viewParameters = [
                            'objectId'     => $email->getId(),
                            'objectAction' => 'view',
                        ];

                        return $this->postActionRedirect(
                            [
                                'returnUrl'       => $this->generateUrl('mautic_email_action', $viewParameters),
                                'viewParameters'  => $viewParameters,
                                'contentTemplate' => 'MauticEmailBundle:Email:view',
                                'flashes' => [
                                    [
                                        'type'    => 'error',
                                        'msg'     => $e->getMessage()
                                    ],
                                ],
                            ]
                        );
                    }
                }
            }

            if ($valid) {
                $viewParameters = [
                    'objectId'     => $email->getId(),
                    'objectAction' => 'view',
                ];

                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $this->generateUrl('mautic_email_action', $viewParameters),
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => 'MauticEmailBundle:Email:view',
                        'passthroughVars' => [
                            'closeModal' => 1,
                        ],
                        'flashes' => [
                            [
                                'type'    => 'notice',
                                'msg'     => $this->translator->trans('mautic.email.send')
                            ],
                        ],
                    ]
                );
            }
        }

        $tmpl = $this->request->get('tmpl', 'index');

        return $this->delegateView(
            [
                'viewParameters'  => [
                    'tmpl'         => $tmpl,
                    'leads'        => $leads,
                    'searchValue'  => $search,
                    'action'       => $action,
                    'form'         => $form->createView(),
                    'currentRoute' => $this->generateUrl(
                        'mautic_plugin_extendee',
                        [
                            'objectAction' => 'sendContactsExampleToEmail',
                            'objectId'     => $email->getId(),
                        ]
                    ),
                ],
                'contentTemplate' => 'MauticExtendeeToolsBundle:Extendee:send_example.html.php',
                'passthroughVars' => [
                    'route'  => false,
                    'target' => ($tmpl == 'update') ? '.lead-merge-options' : null,
                ],
            ]
        );
    }

    /**
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function sendToMailTesterAction($objectId)
    {
        $model  = $this->getModel('email');
        $entity = $model->getEntity($objectId);

        // Prepare a fake lead
        /** @var \Mautic\LeadBundle\Model\FieldModel $fieldModel */
        $fieldModel = $this->getModel('lead.field');
        $fields     = $fieldModel->getFieldList(false, false);
        array_walk(
            $fields,
            function (&$field) {
                $field = "[$field]";
            }
        );
        $fields['id'] = 0;

        $apiKeys = $this->get('mautic.helper.integration')->getIntegrationObject('EMailTester')->getKeys();
        if (empty($apiKeys['mailTesterUsername'])) {
            return new Response($this->translator->trans('plugin.extendee.mail.tester.username.not_blank'));
        }

        $mailTesterUsername = $apiKeys['mailTesterUsername'];

        $clientId = md5(
            $this->get('mautic.helper.user')->getUser()->getEmail().
            $this->coreParametersHelper->getParameter('site_url')
        );
        $uniqueId = $mailTesterUsername.'-'.$clientId.'-'.time();
        $email    = $uniqueId.'@mail-tester.com';

        $users = [
            [
                // Setting the id, firstname and lastname to null as this is a unknown user
                'id'        => '',
                'firstname' => '',
                'lastname'  => '',
                'email'     => $email,
            ],
        ];

        // send test email
        $model->sendSampleEmailToUser($entity, $users, $fields, [], [], false);

        // redirect to mail-tester
        return $this->postActionRedirect(
            [
                'returnUrl' => 'https://www.mail-tester.com/'.$uniqueId,
            ]
        );
    }

    /**
     * Segment rebuild action
     *
     * @param $objectId
     */
    public function segmentRebuildAction($objectId)
    {
        return $this->processJob('lead', 'segment', 'list', $objectId, 'm:s:r');
    }

    /**
     * Campaign rebuild action
     *
     * @param $objectId
     */
    public function campaignRebuildAction($objectId)
    {
        return $this->processJob('campaign', 'campaign', 'campaign', $objectId, ' m:c:r');
    }

    /**
     * Campaign trigger action
     *
     * @param $objectId
     */
    public function campaignTriggerAction($objectId)
    {
        return $this->processJob('campaign', 'campaign', 'campaign', $objectId, ' m:c:t');
    }

    /**
     * Email broadcast send
     *
     * @param $objectId
     */
    public function emailBroadcastSendAction($objectId)
    {
        return $this->processJob('email', 'email', 'email', $objectId, ' mautic:broadcasts:send');
    }


    /**
     * Process job
     *
     * @param $bundle
     * @param $entityName
     * @param $objectId
     * @param $command
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    private function processJob($bundle, $routeContext, $entityName, $objectId, $command)
    {

        $flashes         = [];
        $model           = $this->getModel($bundle.'.'.$entityName);
        $entity          = $model->getEntity($objectId);
        $contentTemplate = 'Mautic'.ucfirst($bundle).'Bundle:'.ucfirst($entityName).':view';
        $activeLink      = '#mautic_'.$routeContext.'_action';
        $mauticContent   = $entityName;
        $returnUrl       = $this->generateUrl(
            'mautic_'.$routeContext.'_action',
            ['objectAction' => 'view', 'objectId' => $entity->getId()]
        );
        $result          = $this->get('mautic.plugin.extendee.helper')->execInBackground($command, $objectId);
        if (!empty($result)) {
            $flashes[] = [
                'type'    => 'notice',
                'msg'     => nl2br(trim($result)),
                'msgVars' => [
                    '%name%' => $entity,
                    '%id%'   => $objectId,
                ],
            ];
        }

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => [
                'objectAction' => 'view',
                'objectId'     => $entity->getId(),
            ],
            'contentTemplate' => $contentTemplate,
            'passthroughVars' => [
                'activeLink'    => $activeLink,
                'mauticContent' => $mauticContent,
            ],
        ];

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }
}
