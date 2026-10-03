<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('rubric_prefixes', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 60);

    // What the filter URL carries: /?prefix=rumor. Kept when the name is
    // edited, so a link someone shared keeps working.
    $table->string('slug', 80)->unique();

    $table->string('color', 7);
    $table->string('icon', 100)->nullable();

    // Only members with rubric.useStaffPrefixes may apply it ("Official").
    $table->boolean('staff_only')->default(false);

    // The tags it may be used in. Null or empty means everywhere.
    $table->json('tag_ids')->nullable();

    $table->unsignedInteger('position')->default(0);
    $table->timestamps();
});
