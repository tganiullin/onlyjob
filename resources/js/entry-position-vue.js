import { createApp } from 'vue';
import AppPosition from './AppPosition.vue';

const el = document.getElementById('public-position-entry');

if (el) {
    createApp(AppPosition, {
        submitUrl: el.dataset.submitUrl ?? '',
        positionTitle: el.dataset.positionTitle ?? '',
        questionsCount: el.dataset.questionsCount ?? 0,
    }).mount(el);
}

