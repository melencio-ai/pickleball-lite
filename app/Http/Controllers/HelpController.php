<?php

namespace App\Http\Controllers;

use App\Services\HelpLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class HelpController extends Controller
{
    private function page(string $view,array $data=[]): Response
    {
        $categories=HelpLibrary::categories();
        $searchIndex=array_map(fn($a)=>array_intersect_key($a,array_flip(['title','description','keywords','categoryTitle','path'])),HelpLibrary::articles());
        return response()->view('help.'.$view,$data+compact('categories','searchIndex'))
            ->header('Cache-Control','public, max-age=300')
            ->header('X-Content-Type-Options','nosniff')
            ->header('Referrer-Policy','no-referrer')
            ->header('X-Robots-Tag',request()->getHost()===config('pickle.help_host')?'index, follow':'noindex, follow');
    }

    public function index(): Response
    {
        return $this->page('index',['title'=>'Help Center','description'=>'Find step-by-step Pickle Lite guides for venue setup, bookings, Open Play, payments and more.','canonical'=>HelpLibrary::canonical()]);
    }

    public function category(string $category): Response
    {
        $group=HelpLibrary::categories()[$category]??null;
        if(!$group)return $this->missing();
        return $this->page('category',['title'=>$group['title'],'description'=>$group['description'],'canonical'=>HelpLibrary::canonical('/category/'.$category),'category'=>$category,'group'=>$group,'articles'=>HelpLibrary::articles($category)]);
    }

    public function article(string $category,string $slug): Response
    {
        $articles=HelpLibrary::articles($category);
        $index=array_search($slug,array_column($articles,'slug'),true);
        if($index===false)return $this->missing();
        $article=$articles[$index];
        $related=array_values(array_filter($articles,fn($a)=>$a['slug']!==$slug));
        $previous=$articles[$index-1]??null;$next=$articles[$index+1]??null;
        return $this->page('article',HelpLibrary::render($article)+['title'=>$article['title'],'description'=>$article['description'],'canonical'=>HelpLibrary::canonical($article['path']),'article'=>$article,'category'=>$category,'related'=>array_slice($related,0,3),'previous'=>$previous,'next'=>$next]);
    }

    public function search(Request $request): Response
    {
        $raw=$request->query('q','');
        $query=is_string($raw)?mb_substr(trim($raw),0,120):'';
        return $this->page('search',['title'=>'Search guides','description'=>'Search Pickle Lite support articles.','query'=>$query,'results'=>HelpLibrary::search($query),'canonical'=>HelpLibrary::canonical('/search')])->header('X-Robots-Tag','noindex, follow')->header('Cache-Control','no-store');
    }

    public function sitemap(): Response
    {
        $paths=['/'];
        foreach(HelpLibrary::categories() as $key=>$group)$paths[]='/category/'.$key;
        foreach(HelpLibrary::articles() as $article)$paths[]=$article['path'];
        $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach($paths as $path)$xml.='<url><loc>'.e(HelpLibrary::canonical($path)).'</loc></url>';
        return response($xml.'</urlset>',200,['Content-Type'=>'application/xml; charset=UTF-8','Cache-Control'=>'public, max-age=3600']);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\nDisallow: /search\nSitemap: ".HelpLibrary::canonical('/sitemap.xml')."\n",200,['Content-Type'=>'text/plain; charset=UTF-8']);
    }

    public function missing(): Response
    {
        return $this->page('missing',['title'=>'Article not found','description'=>'Find the right Pickle Lite guide.','canonical'=>HelpLibrary::canonical()])->setStatusCode(404)->header('X-Robots-Tag','noindex, follow');
    }
}
