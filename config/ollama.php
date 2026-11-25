<?php

return [

    'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),

    'model' => env('OLLAMA_MODEL', 'gemma4:12b'),

    'timeout' => (int) env('OLLAMA_TIMEOUT', 600),

    /*
    | Gemma 4 can spend a long time in a hidden thinking pass.
    | Posts come back as plain text, so thinking stays off.
    */
    'think' => false,

    'num_ctx' => (int) env('OLLAMA_NUM_CTX', 16384),

    'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 3072),

];
