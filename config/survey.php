<?php

return [

    /*
    |-------------------------------------------------------------------------
    | Default survey id
    |-------------------------------------------------------------------------
    |
    | Samples and variables are scoped by survey_id. Single-survey apps can
    | leave this as-is and never pass a survey id; every API that accepts a
    | survey id falls back to this value when given null.
    |
    */
    'survey_id' => env('SURVEY_ID', 'default'),

    /*
    |-------------------------------------------------------------------------
    | Database models
    |-------------------------------------------------------------------------
    |
    | Extend the package models (e.g. add columns to the samples table with
    | your own migration) and point these keys at your subclasses.
    |
    */
    'sample_model' => \Nikoleesg\Survey\Models\Sample::class,

    'variable_model' => \Nikoleesg\Survey\Models\Variable::class,

    'open_answer_model' => \Nikoleesg\Survey\Models\OpenAnswer::class,

    'closed_answer_model' => \Nikoleesg\Survey\Models\Answer::class,

    'paradata_model' => \Nikoleesg\Survey\Models\Paradata::class,

    'persist_chunk_size' => 500,

    /*
    |-------------------------------------------------------------------------
    | Date / time column formats
    |-------------------------------------------------------------------------
    |
    | PHP date formats used to parse DATETIME, DATE and TIME variable columns
    | of the closed-answer export. Adjust them to match your export layout.
    |
    */
    'formats' => [
        'datetime' => 'Y/m/d Hi:s',
        'date'     => 'Y/m/d',
        'time'     => 'Hi',
    ],

];
