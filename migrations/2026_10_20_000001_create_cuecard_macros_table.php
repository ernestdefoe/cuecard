<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('cuecard_macros', function (Blueprint $table) {
            $table->increments('id');
            // Null for a global macro; a personal macro belongs to its author.
            $table->unsignedInteger('user_id')->nullable();
            $table->string('title', 100);
            $table->mediumText('content');
            // What posting a reply with this macro may also do, each only when the
            // poster is allowed to: lock the discussion, tag it, mark the reply best.
            $table->boolean('lock')->default(false);
            $table->json('tag_ids')->nullable();
            $table->boolean('replace_tags')->default(false);
            $table->boolean('best_answer')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Which groups a global macro is offered to (Members: everyone allowed to use macros).
        $schema->create('cuecard_macro_groups', function (Blueprint $table) {
            $table->unsignedInteger('macro_id');
            $table->unsignedInteger('group_id');

            $table->primary(['macro_id', 'group_id']);
            $table->index('group_id');
            $table->foreign('macro_id')->references('id')->on('cuecard_macros')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('cuecard_macro_groups');
        $schema->dropIfExists('cuecard_macros');
    },
];
