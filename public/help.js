(() => {
  const form=document.querySelector('[data-help-search]'), source=document.getElementById('help-search-index');
  if(!form||!source)return;
  const input=form.querySelector('input'),panel=form.querySelector('[data-help-suggestions]'),list=form.querySelector('[data-help-suggestion-list]'),status=form.querySelector('[data-help-search-status]');
  let index=[];try{index=JSON.parse(source.textContent)}catch{return}
  function close(){panel.hidden=true;status.textContent=''}
  function show(){
    const words=input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
    list.replaceChildren();
    if(!words.length){close();return}
    const matches=index.map(a=>{const title=a.title.toLowerCase(),text=[a.description,a.categoryTitle,...a.keywords].join(' ').toLowerCase();return {a,score:words.every(w=>title.includes(w)||text.includes(w))?words.reduce((n,w)=>n+(title.includes(w)?8:2),0):0}}).filter(x=>x.score).sort((a,b)=>b.score-a.score).slice(0,6);
    for(const {a} of matches){const link=document.createElement('a');link.href=a.path;link.textContent=a.title;const category=document.createElement('small');category.textContent=a.categoryTitle;link.append(category);list.append(link)}
    const all=document.createElement('a');all.href='/search?q='+encodeURIComponent(input.value.trim());all.textContent=matches.length?'See all search results':'Search all article content';list.append(all);panel.hidden=false;status.textContent=matches.length+' suggested articles';
  }
  input.addEventListener('input',show);input.addEventListener('focus',()=>{if(input.value.trim())show()});
  form.addEventListener('keydown',e=>{if(e.key==='Escape'){close();input.focus()}});
  document.addEventListener('pointerdown',e=>{if(!form.contains(e.target))close()});
  form.addEventListener('focusout',e=>{if(!form.contains(e.relatedTarget))close()});
})();
