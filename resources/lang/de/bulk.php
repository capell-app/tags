<?php

declare(strict_types=1);

return [
    'manage_pages' => [
        'label' => 'Tags verwalten',
        'modal' => [
            'heading' => 'Tags für ausgewählte Seiten verwalten',
            'form' => [
                'tags_to_attach' => [
                    'label' => 'Zuzuweisende Tags',
                ],
                'tags_to_detach' => [
                    'label' => 'Zu entfernende Tags',
                    'validation' => [
                        'attached_and_detached' => 'Die folgenden Tags können nicht gleichzeitig zugewiesen und entfernt werden: :tags.',
                    ],
                ],
            ],
            'actions' => [
                'save' => [
                    'label' => 'Speichern',
                ],
            ],
        ],
        'notifications' => [
            'updated' => [
                'title' => 'Tags für :count Seiten aktualisiert.',
            ],
        ],
    ],
];
