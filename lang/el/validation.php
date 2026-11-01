<?php

/*
 * Only the rules the game uses are translated; Laravel falls back to its
 * built-in English messages for everything else.
 */
return [
    'required' => 'Το πεδίο «:attribute» είναι υποχρεωτικό.',
    'string' => 'Το πεδίο «:attribute» πρέπει να είναι κείμενο.',
    'integer' => 'Το πεδίο «:attribute» πρέπει να είναι ακέραιος αριθμός.',
    'array' => 'Το πεδίο «:attribute» πρέπει να είναι λίστα.',
    'exists' => 'Η επιλογή για το πεδίο «:attribute» δεν είναι έγκυρη.',
    'in' => 'Η επιλογή για το πεδίο «:attribute» δεν είναι έγκυρη.',
    'min' => [
        'array' => 'Το πεδίο «:attribute» πρέπει να έχει τουλάχιστον :min στοιχεία.',
        'numeric' => 'Το πεδίο «:attribute» πρέπει να είναι τουλάχιστον :min.',
        'string' => 'Το πεδίο «:attribute» πρέπει να έχει τουλάχιστον :min χαρακτήρες.',
    ],
    'max' => [
        'array' => 'Το πεδίο «:attribute» δεν μπορεί να έχει πάνω από :max στοιχεία.',
        'numeric' => 'Το πεδίο «:attribute» δεν μπορεί να είναι πάνω από :max.',
        'string' => 'Το πεδίο «:attribute» δεν μπορεί να έχει πάνω από :max χαρακτήρες.',
    ],
    'between' => [
        'array' => 'Το πεδίο «:attribute» πρέπει να έχει από :min έως :max στοιχεία.',
        'numeric' => 'Το πεδίο «:attribute» πρέπει να είναι από :min έως :max.',
        'string' => 'Το πεδίο «:attribute» πρέπει να έχει από :min έως :max χαρακτήρες.',
    ],
    'attributes' => [
        'name' => 'όνομα',
        'code' => 'κωδικός',
        'total_rounds' => 'γύροι',
        'seconds_per_question' => 'δευτερόλεπτα ανά ερώτηση',
        'categories' => 'κατηγορίες',
    ],
];
