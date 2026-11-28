<?php

namespace App\Services;

use Illuminate\Support\Str;

final class HelpLibrary
{
    public static function catalog(): array
    {
        $raw=file_get_contents(base_path('docs/help/index.json'));
        return json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    }

    public static function categories(): array { return self::catalog()['categories']; }

    public static function articles(?string $category=null): array
    {
        $result=[];
        foreach(self::categories() as $key=>$group) {
            if($category!==null && $key!==$category) continue;
            foreach($group['articles'] as $article) $result[]=$article+['category'=>$key,'categoryTitle'=>$group['title'],'path'=>'/articles/'.$key.'/'.$article['slug']];
        }
        return $result;
    }

    public static function markdown(array $article): string
    {
        $category=$article['category'];$slug=$article['slug'];
        if(!preg_match('/^[a-z0-9-]+$/D',$category) || !preg_match('/^[a-z0-9-]+$/D',$slug)) throw new \RuntimeException('Invalid help article path.');
        return file_get_contents(base_path("docs/help/{$category}/{$slug}.md"));
    }

    public static function render(array $article): array
    {
        $markdown=self::markdown($article);
        $html=Str::markdown($markdown,['html_input'=>'strip','allow_unsafe_links'=>false]);
        $toc=[];$seen=[];
        $html=preg_replace_callback('~<h2>(.*?)</h2>~s',function($m)use(&$toc,&$seen) {
            $label=html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8');
            $base=Str::slug($label) ?: 'section';
            $count=($seen[$base]??0)+1;$seen[$base]=$count;
            $id=$base.($count>1?'-'.$count:'');
            $toc[]=['id'=>$id,'title'=>$label];
            return '<h2 id="'.e($id).'">'.$m[1].'</h2>';
        },$html);
        return ['html'=>$html,'toc'=>$toc,'minutes'=>max(1,(int)ceil(str_word_count(strip_tags($html))/180))];
    }

    public static function search(string $query): array
    {
        $words=array_values(array_filter(preg_split('/[^\pL\pN]+/u',Str::lower(trim($query)))?:[]));
        if(!$words)return [];
        $results=[];
        foreach(self::articles() as $article) {
            $title=Str::lower($article['title']);
            $summary=Str::lower($article['description'].' '.$article['categoryTitle'].' '.implode(' ',$article['keywords']));
            $body=Str::lower(self::markdown($article));
            $score=0;
            foreach($words as $word) {
                $weight=(str_contains($title,$word)?8:0)+(str_contains($summary,$word)?4:0)+(str_contains($body,$word)?1:0);
                if(!$weight)continue 2;
                $score+=$weight;
            }
            $results[]=$article+['score'=>$score];
        }
        usort($results,fn($a,$b)=>$b['score']<=>$a['score']);
        return array_slice($results,0,50);
    }

    public static function appLink(?string $path=null): ?string
    {
        $url=config('pickle.help_url');
        if(!is_string($url) || !preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?/?$~iD',$url))return null;
        return rtrim($url,'/').($path??'');
    }

    public static function contextPath(): ?string
    {
        return match(true) {
            request()->routeIs('app.staff.calendar*')=>'/articles/court-bookings/calendar',
            request()->routeIs('app.staff.open-play*')=>'/articles/open-play/create-session',
            request()->routeIs('app.settings.customization')=>'/articles/payments/configure-qr',
            request()->routeIs('app.settings.website*')=>'/articles/venue-website/branding',
            request()->routeIs('app.settings')=>'/articles/venue-administration/courts',
            request()->routeIs('app.settings.subscription*')=>'/articles/subscriptions/plans',
            request()->routeIs('app.settings.rewards*')=>'/articles/rewards/overview',
            request()->routeIs('app.settings.onboarding*')=>'/articles/getting-started/venue-setup',
            default=>null
        };
    }

    public static function canonical(string $path='/'): string
    {
        return 'https://'.config('pickle.help_host').$path;
    }
}
