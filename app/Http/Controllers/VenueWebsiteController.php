<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\VenueWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VenueWebsiteController extends Controller
{
    public function index(Request $request)
    {
        $venue = $request->user()->venue;
        $content = $venue->website_content ?? [];
        $media = $venue->media()->get(['kind', 'version'])->keyBy('kind');
        return view('staff.website', compact('venue', 'content', 'media'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'show_demo_gallery' => ['sometimes', 'boolean'],
            'headline' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:350'],
            'introduction' => ['nullable', 'string', 'max:1500'],
            'facilities' => ['sometimes', 'array', 'max:10'],
            'facilities.*' => ['string', 'distinct', Rule::in(array_keys(VenueWebsite::FACILITIES))],
            'custom_facilities' => ['nullable', 'string', 'max:700'],
            'faq_questions' => ['sometimes', 'array', 'max:5'],
            'faq_questions.*' => ['nullable', 'string', 'max:120'],
            'faq_answers' => ['sometimes', 'array', 'max:5'],
            'faq_answers.*' => ['nullable', 'string', 'max:500'],
            'seo_title' => ['nullable', 'string', 'max:100'],
            'seo_description' => ['nullable', 'string', 'max:200'],
        ]);
        $content = collect($data)->only(['headline','description','introduction','seo_title','seo_description'])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')->all();
        $content['facilities'] = array_values($data['facilities'] ?? []);
        $content['custom_facilities'] = collect(preg_split('/\R/u', $data['custom_facilities'] ?? ''))
            ->map(fn ($line) => trim($line))->filter()->unique()->take(10)->values()->all();
        $content['faqs'] = [];
        $faqKeys = array_unique(array_merge(array_keys($data['faq_questions'] ?? []), array_keys($data['faq_answers'] ?? [])));
        foreach ($faqKeys as $i) {
            $question = trim($data['faq_questions'][$i] ?? '');
            $answer = trim($data['faq_answers'][$i] ?? '');
            if (($question === '') !== ($answer === '')) {
                return back()->withErrors(['faqs' => 'Enter both a question and an answer for each FAQ.'])->withInput();
            }
            if ($question !== '') $content['faqs'][] = ['question' => $question, 'answer' => $answer];
        }
        DB::transaction(function () use ($request, $content) {
            $venue = Venue::query()->lockForUpdate()->findOrFail($request->user()->venue_id);
            $content['show_demo_gallery'] = $request->has('show_demo_gallery')
                ? $request->boolean('show_demo_gallery')
                : ($venue->website_content['show_demo_gallery'] ?? true);
            $venue->update(['website_content' => $content]);
        }, 3);

        return redirect()->route('settings.website')->with('status', 'Website content saved.');
    }

    public function gallery(Request $request, string $kind)
    {
        abort_unless(in_array($kind, VenueWebsite::GALLERY, true), 404);
        $data = $request->validate([
            'photo' => ['required_without:remove', 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:max_width=4096,max_height=4096', 'max:2048'],
            'remove' => ['sometimes', 'accepted'],
        ]);
        DB::transaction(function () use ($request, $kind, $data) {
            $venue = Venue::query()->lockForUpdate()->findOrFail($request->user()->venue_id);
            if ($request->boolean('remove')) {
                $venue->media()->where('kind', $kind)->delete();
            } else {
                $file = $data['photo'];
                $bytes = $file->get();
                $venue->media()->updateOrCreate(['kind' => $kind], [
                    'mime' => $file->getMimeType(), 'version' => hash('sha256', $bytes), 'content' => base64_encode($bytes),
                ]);
            }
        }, 3);

        return redirect()->to(route('settings.website').'#gallery')->with('status', 'Gallery updated.');
    }
}
