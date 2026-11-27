<?php

namespace Tests\Feature;

use App\Services\WalkthroughVideo;
use Tests\TestCase;

class WalkthroughVideoTest extends TestCase
{
    public function test_only_supported_https_video_links_are_normalized(): void
    {
        foreach ([
            'https://youtu.be/AbCdEf12345'=>'https://www.youtube-nocookie.com/embed/AbCdEf12345',
            'https://www.youtube.com/watch?v=AbCdEf12345&feature=share'=>'https://www.youtube-nocookie.com/embed/AbCdEf12345',
            'https://vimeo.com/123456789/abc123def'=>'https://player.vimeo.com/video/123456789',
            'https://www.loom.com/share/0123456789abcdef0123456789abcdef'=>'https://www.loom.com/embed/0123456789abcdef0123456789abcdef',
        ] as $url=>$embed) {
            config(['pickle.walkthrough_video_url'=>$url]);
            $this->assertStringStartsWith($embed,WalkthroughVideo::source()['embed']);
        }
        foreach ([null,'','javascript:alert(1)','http://youtu.be/AbCdEf12345',
            'https://youtube.com.attacker.example/watch?v=AbCdEf12345','https://evil.example/?v=AbCdEf12345',
            'https://user@youtu.be/AbCdEf12345','https://www.youtube.com/watch?v[]=AbCdEf12345',
            'https://www.youtube.com/watch?v=AbCdEf12345%22%3E','https://www.loom.com/share/not-an-id'] as $url) {
            config(['pickle.walkthrough_video_url'=>$url]);
            $this->assertNull(WalkthroughVideo::source());
        }
    }
}
