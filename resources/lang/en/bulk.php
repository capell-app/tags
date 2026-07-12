<?php

declare(strict_types=1);

return [
    'manage_pages' => [
        'label' => 'Manage tags',
        'modal' => [
            'heading' => 'Manage tags for selected pages',
            'form' => [
                'tags_to_attach' => [
                    'label' => 'Tags to attach',
                ],
                'tags_to_detach' => [
                    'label' => 'Tags to detach',
                    'validation' => [
                        'attached_and_detached' => 'The following tags cannot be attached and detached at the same time: :tags.',
                    ],
                ],
            ],
            'actions' => [
                'save' => [
                    'label' => 'Save',
                ],
            ],
        ],
        'notifications' => [
            'updated' => [
                'title' => 'Updated tags for :count pages.',
            ],
        ],
    ],
];
