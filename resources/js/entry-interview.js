import { createApp } from 'vue';
import App from './App.vue';

const el = document.getElementById('public-interview-run');
if (el) {
    const questions = JSON.parse(el.dataset.questions ?? '[]');
    const questionsArray = Array.isArray(questions) ? questions : [];

    createApp(App, {
        questions: questionsArray,
        answerEndpointTemplate: el.dataset.answerEndpointTemplate ?? '',
        transcribeEndpoint: el.dataset.transcribeEndpoint ?? '',
        answerTimeSeconds: Number.parseInt(el.dataset.answerTimeSeconds ?? '120', 10),
        interviewCompleted: el.dataset.interviewCompleted === '1',
        completionMessage:
            el.dataset.completionMessage ??
            'Спасибо! Вы успешно завершили первый этап интервью.',
        firstName: el.dataset.firstName ?? '',
        lastName: el.dataset.lastName ?? '',
        positionTitle: el.dataset.positionTitle ?? '',
        answerTimeLabel: el.dataset.answerTimeLabel ?? '2 минуты',
        logoUrl: el.dataset.logoUrl ?? '',
    }).mount(el);
}
