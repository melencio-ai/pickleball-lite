<?php

namespace App\Services;

final class WalkthroughVideo
{
    /** Normalize trusted video providers; never embed an arbitrary URL. */
    public static function source(): ?array
    {
        $url = config('pickle.walkthrough_video_url');
        if (!is_string($url) || strlen($url)>2048 || preg_match('/[\x00-\x20\x7f\\\\]/',trim($url))) return null;
        $parts = parse_url(trim($url));
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' ||
            isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) return null;
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '',$query);
        $id = null;

        if ($host === 'youtu.be') {
            if (preg_match('~^/([A-Za-z0-9_-]{11})/?$~D',$path,$match)) $id=$match[1];
        } elseif (in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com'],true)) {
            if ($path === '/watch' && is_string($query['v'] ?? null)) $id=$query['v'];
            elseif (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})/?$~D',$path,$match)) $id=$match[1];
        }
        if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D',$id)) {
            return ['embed'=>'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0&autoplay=1','watch'=>'https://www.youtube.com/watch?v='.$id];
        }
        if (in_array($host,['vimeo.com','www.vimeo.com','player.vimeo.com'],true) &&
            preg_match('~^/(?:video/)?([0-9]{1,15})(?:/([A-Za-z0-9]{6,64}))?/?$~D',$path,$match)) {
            $hash=$match[2] ?? ($query['h'] ?? '');
            if (!is_string($hash) || ($hash!=='' && !preg_match('/^[A-Za-z0-9]{6,64}$/D',$hash))) return null;
            return ['embed'=>'https://player.vimeo.com/video/'.$match[1].'?autoplay=1&dnt=1'.($hash!==''?'&h='.$hash:''),
                'watch'=>'https://vimeo.com/'.$match[1].($hash!==''?'/'.$hash:'')];
        }
        if (in_array($host,['loom.com','www.loom.com'],true) && preg_match('~^/(?:share|embed)/([a-fA-F0-9]{32})/?$~D',$path,$match)) {
            return ['embed'=>'https://www.loom.com/embed/'.$match[1].'?autoplay=1','watch'=>'https://www.loom.com/share/'.$match[1]];
        }
        return null;
    }
}
