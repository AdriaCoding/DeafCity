/* tag-input.js — shared chip tag input with autocomplete */
(function () {
    'use strict';

    function addChip(tag, chipBox, textInput) {
        var existing = getTags(chipBox);
        if (existing.indexOf(tag) !== -1) {
            textInput.value = '';
            return;
        }
        var chip = document.createElement('span');
        chip.className = 'chip';
        chip.dataset.tag = tag;
        chip.textContent = tag + ' ';
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'chip-remove';
        removeBtn.title = 'Elimina';
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', function () { chip.remove(); });
        chip.appendChild(removeBtn);
        chipBox.insertBefore(chip, textInput);
        textInput.value = '';
    }

    function showSuggestions(textInput, chipBox, suggestions, allTags) {
        var val = textInput.value.trim().toLowerCase();
        if (!val) {
            hideSuggestions(suggestions);
            return;
        }
        var existing = getTags(chipBox);
        var matches = allTags.filter(function (t) {
            return t.toLowerCase().startsWith(val) && existing.indexOf(t) === -1;
        }).slice(0, 8);
        if (!matches.length) {
            hideSuggestions(suggestions);
            return;
        }
        suggestions.innerHTML = '';
        matches.forEach(function (tag) {
            var item = document.createElement('div');
            item.className = 'tag-suggestion-item';
            item.textContent = tag;
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                addChip(tag, chipBox, textInput);
                hideSuggestions(suggestions);
            });
            suggestions.appendChild(item);
        });
        suggestions.style.display = 'block';
    }

    function hideSuggestions(suggestions) {
        suggestions.style.display = 'none';
        suggestions.innerHTML = '';
    }

    function focusSuggestion(suggestions, direction) {
        var items = suggestions.querySelectorAll('.tag-suggestion-item');
        if (!items.length) return;
        var current = suggestions.querySelector('.focused');
        var idx = current ? Array.from(items).indexOf(current) + direction : (direction > 0 ? 0 : items.length - 1);
        idx = Math.max(0, Math.min(items.length - 1, idx));
        if (current) current.classList.remove('focused');
        items[idx].classList.add('focused');
    }

    function getTags(chipBox) {
        return Array.from(chipBox.querySelectorAll('.chip')).map(function (c) { return c.dataset.tag; });
    }

    function clear(chipBox, textInput) {
        chipBox.querySelectorAll('.chip').forEach(function (chip) { chip.remove(); });
        textInput.value = '';
    }

    function setTags(tags, chipBox, textInput) {
        clear(chipBox, textInput);
        tags.forEach(function (tag) {
            if (tag) addChip(tag, chipBox, textInput);
        });
    }

    function init(chipBox, textInput, suggestionsEl, allTags) {
        var tags = Array.isArray(allTags) ? allTags : [];

        chipBox.addEventListener('click', function (e) {
            if (e.target === chipBox) textInput.focus();
        });

        chipBox.querySelectorAll('.chip-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.chip').remove();
            });
        });

        textInput.addEventListener('input', function () {
            showSuggestions(textInput, chipBox, suggestionsEl, tags);
        });

        textInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                var val = textInput.value.trim().replace(/,$/, '');
                if (val) addChip(val, chipBox, textInput);
                hideSuggestions(suggestionsEl);
            } else if (e.key === 'Backspace' && textInput.value === '') {
                var chips = chipBox.querySelectorAll('.chip');
                if (chips.length) chips[chips.length - 1].remove();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                focusSuggestion(suggestionsEl, 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                focusSuggestion(suggestionsEl, -1);
            } else if (e.key === 'Escape') {
                hideSuggestions(suggestionsEl);
            }
        });

        textInput.addEventListener('blur', function () {
            setTimeout(function () { hideSuggestions(suggestionsEl); }, 150);
        });
    }

    window.TagInput = {
        init: init,
        getTags: getTags,
        addChip: addChip,
        clear: clear,
        setTags: setTags,
    };
}());
