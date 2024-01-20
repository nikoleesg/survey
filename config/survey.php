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
    | Database model
    |-------------------------------------------------------------------------
    |
    */
    'open_answer_model' => \Nikoleesg\Survey\Models\OpenAnswer::class,

    'closed_answer_model' => \Nikoleesg\Survey\Models\Answer::class,

    'paradata_model' => \Nikoleesg\Survey\Models\Paradata::class,

    'persist_chunk_size' => 500,

];
