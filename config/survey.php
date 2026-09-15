<?php

return [

    /*
    |-------------------------------------------------------------------------
    | Database table prefix
    |-------------------------------------------------------------------------
    |
    */
    'table_prefix' => 'survey_',

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

];
