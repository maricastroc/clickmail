<?php

declare(strict_types = 1);

use App\Models\Campaign;
use App\Models\CampaignMail;
use App\Models\EmailList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Http\Response;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('Campaign statistics are displayed correctly', function () {
    $this->withoutExceptionHandling();

    $campaign = Campaign::factory()->create(['user_id' => $this->user->id, 'deleted_at' => null]);

    $subscriber = Subscriber::factory()->create([
        'email_list_id' => EmailList::factory()->create()->id,
    ]);

    CampaignMail::factory()->count(5)->create([
        'campaign_id'   => $campaign->id,
        'opens'         => 3,
        'clicks'        => 2,
        'subscriber_id' => $subscriber->id,
    ]);

    $response = $this->getJson(route('campaign.statistics', ['campaign' => $campaign]));

    $response->assertStatus(Response::HTTP_OK);

    $response->assertJsonFragment([
        'total_emails' => 5,
        'total_opens'  => 15,
        'total_clicks' => 10,
    ]);
});
