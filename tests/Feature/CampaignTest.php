<?php

use App\Models\Subscriber;
use App\Models\EmailList;
use App\Models\Template;
use App\Models\User;
use App\Models\Campaign;
use App\Models\CampaignMail;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use App\Jobs\SendEmailsCampaignJob;
use App\Mail\EmailCampaign;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('I should be able to get all my user campaigns', function () {
    Campaign::factory()->count(15)->create(['user_id' => $this->user->id, 'deleted_at' => null]);
    
    $response = $this->getJson(route('dashboard'));
    
    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'subject',
                    'status',
                    'send_at',
                    'track_click',
                    'track_open',
                    'email_list_id',
                    'template_id',
                    'user_id',
                    'created_at',
                    'updated_at',
                ]
            ],
            'links',
        ])
        ->assertJsonCount(10, 'data');
});

test('I should be able to search campaigns by name', function () {
    $campaign1 = Campaign::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Campaign Alpha',
        'deleted_at' => null,
    ]);
    
    $campaign2 = Campaign::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Campaign Beta',
        'deleted_at' => null,
    ]);
    
    $response = $this->getJson(route('dashboard', ['search' => 'Alpha']));
    
    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $campaign1->id);
});

test('I should be able to filter to include deleted campaigns', function () {
    $activeCampaign = Campaign::factory()->create([
        'user_id' => $this->user->id,
        'deleted_at' => null
    ]);

    $deletedCampaign = Campaign::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $deletedCampaign->delete();

    $response = $this->getJson(route('dashboard'));
    $response->assertStatus(Response::HTTP_OK)
      ->assertJsonCount(1, 'data');

    $response = $this->getJson(route('dashboard', ['withTrashed' => true]));
    $response->assertStatus(Response::HTTP_OK)
      ->assertJsonCount(2, 'data');
});

test('I should not be able to see other users campaigns', function () {
    $otherUser = User::factory()->create();
    Campaign::factory()->count(3)->create(['user_id' => $otherUser->id]);
    
    $response = $this->getJson(route('dashboard'));
    
    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonCount(0, 'data');
});

test('I should be able to access the create campaign page', function () {
    $emailList = EmailList::factory()->create(['user_id' => $this->user->id]);
    
    $response = $this->get(route('campaigns.create'));
    
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard/Form')
        ->has('emailLists')
        ->has('templates')
    );
});

test('I should be able to access the edit campaign page', function () {
    $campaign = Campaign::factory()->create(['user_id' => $this->user->id, 'deleted_at' => null]);

    $response = $this->get(route('campaigns.edit', $campaign));

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard/Form')
        ->where('isEdit', true)
        ->has('campaign')
        ->has('emailLists')
        ->has('templates')
    );
});

test('I should be able to create a campaign draft', function () {
    $payload = Campaign::factory()->make(['draft_mode' => true, 'step' => 3, 'send_at' => null])->toArray();

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Campaign draft successfully created!']);

    $this->assertDatabaseHas('campaigns', ['name' => $payload['name']]);
});

test('I should be able to schedule a campaign to send in the future', function () {
    Bus::fake();

    $payload = Campaign::factory()->make([
        'draft_mode' => false,
        'step' => 3,
        'send_at' => now()->addMinutes(10)->toDateTimeString(),
        'customize_send_at' => true,
    ])->toArray();

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(200);

    Bus::assertDispatched(SendEmailsCampaignJob::class, function ($job) use ($payload) {
        $sendAt = \Carbon\Carbon::parse($payload['send_at']);

        if ($job->delay instanceof \DateTimeInterface) {
            return $job->delay->getTimestamp() === $sendAt->getTimestamp();
        } elseif (is_int($job->delay)) {
            $expectedDelay = $sendAt->diffInSeconds(now());
            return abs($job->delay - $expectedDelay) < 5;
        }

        return false;
    });
});

test('I should be able to send a campaign immediately', function () {
    Bus::fake();

    $payload = Campaign::factory()->make([
        'draft_mode' => false,
        'step' => 3,
        'send_at' => now()->toDateTimeString(),
        'customize_send_at' => true,
    ])->toArray();

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(200);

    Bus::assertDispatched(SendEmailsCampaignJob::class, function ($job) {
        return $job->delay === null || $job->delay === 0;
    });
});

test('I should be able to update a campaign draft', function () {
    $campaign = Campaign::factory()->create(['user_id' => $this->user->id, 'deleted_at' => null]);

    $emailList = EmailList::factory()->create(['user_id' => $this->user->id]);
    $template = Template::factory()->create(['user_id' => $this->user->id]);

    $response = $this->putJson(route('campaigns.update', $campaign->id), [
        'name' => 'Updated Campaign',
        'subject' => 'Updated Subject',
        'email_list_id' => $emailList->id,
        'template_id' => $template->id,
        'track_click' => true,
        'track_open' => true,
        'body' => '<p>Updated Body</p>',
        'send_at' => now()->addHour()->toDateTimeString(),
        'customize_send_at' => true,
        'step' => 3,
        'draft_mode' => true,
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Campaign draft successfully updated!']);

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'name' => 'Updated Campaign',
        'subject' => 'Updated Subject',
    ]);
});

test('It should create a job for each subscriber when campaign is sent', function () {
    Bus::fake();
    
    $user = User::factory()->create();
    $this->actingAs($user);

    $emailList = EmailList::factory()
        ->hasSubscribers(5)
        ->create(['user_id' => $user->id, 'deleted_at' => null]);

    $template = Template::factory()->create(['user_id' => $user->id, 'deleted_at' => null]);

    $payload = [
        'name' => 'Test Campaign',
        'subject' => 'Test Subject',
        'email_list_id' => $emailList->id,
        'template_id' => $template->id,
        'body' => '<p>Test Body</p>',
        'send_at' => now()->addHour()->toDateTimeString(),
        'customize_send_at' => true,
        'step' => 3,
        'draft_mode' => false,
        'track_click' => true,
        'track_open' => true,
    ];

    $response = $this->postJson(route('campaigns.store'), $payload);
    $response->assertStatus(200);

    $campaign = Campaign::where('name', 'Test Campaign')->first();

    Bus::assertDispatched(SendEmailsCampaignJob::class, function ($job) use ($campaign) {
        return $job->campaign->id === $campaign->id;
    });

    $job = new SendEmailsCampaignJob($campaign);
    $job->handle();

    Bus::assertDispatched(SendEmailsCampaignJob::class, function ($job) use ($campaign) {
        return $job->campaign->id === $campaign->id;
    }, 5);
});

test('I should not be able to restore a campaign that does not belong to me', function () {
    $otherUser = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $otherUser->id, 'deleted_at' => null]);
    $campaign->delete();

    $response = $this->putJson(route('campaigns.restore', $campaign->id));
    
    $response->assertStatus(403);
});

test('I should not be able to create a campaign with past dates for scheduling', function () {
    $payload = Campaign::factory()->make([
        'draft_mode' => false,
        'step' => 3,
        'send_at' => now()->subDay()->toDateTimeString(),
    ])->toArray();

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['send_at']);
});

test('I should not be able to create a campaign without a valid step', function () {
    $payload = Campaign::factory()->make()->toArray();

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['step']);
});

test('I should be able to create a campaign without step 1 valid data on draft mode', function () {
    $payload = [
        'step' => 1,
        'draft_mode' => true
    ];

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'subject', 'email_list_id', 'template_id', 'track_click', 'track_open']);
});

test('I should not be able to create a campaign without step 1 valid data', function () {
    $payload = ['step' => 1];

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'subject', 'email_list_id', 'template_id', 'track_click', 'track_open']);
});

test('I should be able to create a campaign without step 2 valid data on draft mode', function () {
    $payload = [
        'step' => 2,
        'draft_mode' => true,
    ];

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

test('I should not be able to create a campaign without step 2 valid data', function () {
    $payload = [
        'step' => 2,
    ];

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

test('I should not be able to create a campaign without step 3 valid data on draft mode', function () {
    $emailList = EmailList::factory()->create();
    $template = Template::factory()->create();

    $payload = [
        'step' => 3,
        'name' => 'Campaign test 3',
        'subject' => 'Subject test 3',
        'email_list_id' => $emailList->id,
        'template_id' => $template->id,
        'track_click' => true,
        'track_open' => true,
        'body' => 'Campaign Body',
        'draft_mode' => true,
    ];

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(200);
});

test('I should not be able to create a campaign without step 3 valid data', function () {
    $emailList = EmailList::factory()->create();
    $template = Template::factory()->create();

    $payload = [
        'step' => 3,
        'name' => 'Campaign test 3',
        'subject' => 'Subject test 3',
        'email_list_id' => $emailList->id,
        'template_id' => $template->id,
        'track_click' => true,
        'track_open' => true,
        'body' => 'Campaign Body',
        'draft_mode' => false,
    ];

    $response = $this->postJson(route('campaigns.store'), $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['send_at', 'customize_send_at']);
});

test('Links on the body should be replaced with the tracking line', function () {
    $emailList = EmailList::factory()->create();
    $subscriber = Subscriber::factory()->create(['email_list_id' => $emailList->id]);
    
    $template = Template::factory()->create([
        'body' => '<div>Hello, world! <a href="http://www.google.com">Click here</a> </div>'
    ]);

    $campaign = Campaign::factory()->create([
        'email_list_id' => $emailList->id,
        'template_id' => $template->id,
        'body' => $template->body, 
        'send_at' => now()->addDays(2)->format('Y-m-d'),
        'track_click' => true,
        'user_id' => $this->user->id
    ]);

    $mail = CampaignMail::create([
        'campaign_id' => $campaign->id, 
        'subscriber_id' => $subscriber->id, 
        'send_at' => $campaign->send_at
    ]);

    $renderedMail = (new EmailCampaign($campaign, $mail))->render();

    $pattern = '/href="([^"]*)"/';
    preg_match_all($pattern, $renderedMail, $matches);

    $expectedUrl = route('tracking.clicks', ['mail' => $mail, 'url' => 'http://www.google.com']);

    foreach ($matches[1] as $index => $value) {
    if (!str_contains($value, '/track/click/')) {
        continue;
    }
    expect($value)->toBe(route('tracking.clicks', ['mail' => $mail, 'url' => 'http://www.google.com']));
}
});

test('Tracking click increments count and redirects', function () {
    $this->withoutExceptionHandling();
    
    $campaign = Campaign::factory()->create(['track_click' => true, 'deleted_at' => null]);
    $campaignMail = CampaignMail::factory()->create([
        'clicks' => 0,
        'campaign_id' => $campaign->id
    ]);

    $campaignMail->load('campaign');

    $originalUrl = 'http://www.google.com';
    $response = $this->get(route('tracking.clicks', ['mail' => $campaignMail->id, 'url' => $originalUrl]));

    expect($campaignMail->refresh()->clicks)->toBe(1);
    $response->assertRedirect($originalUrl);
});

test('Tracking click does not increment if track_click is disabled', function () {
    $this->withoutExceptionHandling();
    
    $campaign = Campaign::factory()->create(['track_click' => false, 'deleted_at' => null]);
    $campaignMail = CampaignMail::factory()->create([
        'clicks' => 0,
        'campaign_id' => $campaign->id
    ]);

    $campaignMail->load('campaign');

    $originalUrl = 'http://www.google.com';
    $response = $this->get(route('tracking.clicks', ['mail' => $campaignMail->id, 'url' => $originalUrl]));

    expect($campaignMail->refresh()->clicks)->toBe(0);
});

test('Tracking opening increments count', function () {
    $this->withoutExceptionHandling();
    
    $campaign = Campaign::factory()->create(['track_open' => true, 'deleted_at' => null]);
    $campaignMail = CampaignMail::factory()->create([
        'campaign_id' => $campaign->id,
        'opens' => 0
    ]);

    $campaignMail->load('campaign');

    $response = $this->get(route('tracking.openings', ['mail' => $campaignMail]));

    expect($campaignMail->refresh()->opens)->toBe(1);
    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'image/gif');
});

test('Tracking opening does not increment if tracking is disabled', function () {
    $this->withoutExceptionHandling();
    
    $campaign = Campaign::factory()->create(['track_open' => false, 'deleted_at' => null]);

    $campaignMail = CampaignMail::factory()->create([
        'campaign_id' => $campaign->id,
        'opens' => 0,
        'deleted_at' => null
    ]);

        $campaignMail->load('campaign');

    $response = $this->get(route('tracking.openings', ['mail' => $campaignMail]));

    expect($campaignMail->refresh()->opens)->toBe(0);
});