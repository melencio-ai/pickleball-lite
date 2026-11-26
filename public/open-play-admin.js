const repeat = document.querySelector('[data-repeat]');
const occurrences = document.querySelector('[data-occurrences]');
if (repeat && occurrences) {
    const update = () => {
        occurrences.hidden = repeat.value === 'none';
        occurrences.querySelector('input').disabled = occurrences.hidden;
    };
    repeat.addEventListener('change', update);
    update();
}
