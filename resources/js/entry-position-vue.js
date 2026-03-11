import { createApp } from 'vue';
import AppPosition from './AppPosition.vue';

const el = document.getElementById('public-position-entry');

if (el) {
    createApp(AppPosition, {
        submitUrl: el.dataset.submitUrl ?? '',
        positionTitle: el.dataset.positionTitle ?? '',
        questionsCount: Number(el.dataset.questionsCount) || 0,
        answerTimeSeconds: Number(el.dataset.answerTimeSeconds) || 120,
        policyUrl: el.dataset.policyUrl ?? '#',
    }).mount(el);
}

