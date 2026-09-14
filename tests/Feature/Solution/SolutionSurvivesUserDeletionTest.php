<?php

use App\Models\Profile;
use App\Models\Solution;
use App\Models\User;

/**
 * Covers a real data-loss bug surfaced during review: solutions.author_id
 * was cascadeOnDelete() on profiles, and deleting a user already cascades
 * to their profile -- so deleting a user account silently destroyed every
 * knowledge-base article they wrote. author_id is now nullable with
 * nullOnDelete(), so the solution survives and just loses its attribution.
 */
function createUserWithProfileForSolutionTest(): User
{
    $user = User::factory()->create();

    Profile::create([
        'user_id' => $user->id,
        'display_name' => 'Solution Author',
        'name' => ['firstname' => 'Solution', 'lastname' => 'Author'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    return $user;
}

test('deleting a user preserves solutions they authored, with author cleared', function () {
    $user = createUserWithProfileForSolutionTest();
    $profile = $user->profile;

    $solution = Solution::create([
        'author_id' => $profile->id,
        'title' => 'Fix printer offline error',
        'description' => 'Restart the print spooler service.',
    ]);

    $user->delete();

    $solution->refresh();

    expect($solution)->not->toBeNull();
    expect($solution->author_id)->toBeNull();
});
