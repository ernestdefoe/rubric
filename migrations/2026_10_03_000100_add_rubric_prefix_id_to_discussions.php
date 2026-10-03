<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('discussions', function (Blueprint $table) {
            // Deleting a prefix leaves its discussions unprefixed, not gone.
            $table->unsignedInteger('rubric_prefix_id')->nullable();
            $table->foreign('rubric_prefix_id')->references('id')->on('rubric_prefixes')->nullOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('discussions', function (Blueprint $table) {
            $table->dropForeign(['rubric_prefix_id']);
            $table->dropColumn('rubric_prefix_id');
        });
    },
];
