const rootElement = document.getElementById('public-interview-run');

if (rootElement instanceof HTMLElement) {
    const parsedQuestions = JSON.parse(rootElement.dataset.questions ?? '[]');
    const questions = Array.isArray(parsedQuestions) ? parsedQuestions : [];
    const answerEndpointTemplate = rootElement.dataset.answerEndpointTemplate ?? '';
    const transcribeEndpoint = rootElement.dataset.transcribeEndpoint ?? '';
    const answerTimeSeconds = Number.parseInt(rootElement.dataset.answerTimeSeconds ?? '120', 10);
    const interviewCompletedFromServer = rootElement.dataset.interviewCompleted === '1';
    const completionMessage = rootElement.dataset.completionMessage
        ?? 'Спасибо! Вы завершили интервью. Ваши ответы успешно сохранены.';
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    const screenStart = document.getElementById('screen-start');
    const screenChat = document.getElementById('screen-chat');
    const screenInterview = document.getElementById('screen-interview');
    const startFlowButton = document.getElementById('start-flow');

    const microphoneAccessButton = document.getElementById('microphone-access');
    const phraseRecordToggleButton = document.getElementById('phrase-record-toggle');
    const microphoneStatusElement = document.getElementById('microphone-status');
    const phraseUserRow = document.getElementById('phrase-user-row');
    const phraseResultElement = document.getElementById('phrase-result');
    const phraseSuccessRow = document.getElementById('phrase-success-row');
    const chatContinueButton = document.getElementById('chat-continue');

    const instructionsModal = document.getElementById('instructions-modal');
    const instructionsStartButton = document.getElementById('instructions-start');

    const questionList = document.getElementById('question-list');
    const recordToggleButton = document.getElementById('record-toggle');
    const skipAnswerButton = document.getElementById('skip-answer');
    const answerStatusElement = document.getElementById('answer-status');
    const speechFallbackElement = document.getElementById('speech-fallback');

    const mediaRecorderSupported = typeof window.MediaRecorder !== 'undefined';
    const canRequestMicrophone = typeof navigator.mediaDevices?.getUserMedia === 'function';
    const recordingSupported = mediaRecorderSupported && canRequestMicrophone;

    const initialSubmittedAnswers = new Map(
        questions
            .filter(
                (question) => typeof question?.candidate_answer === 'string'
                    && question.candidate_answer.trim() !== '',
            )
            .map((question) => [question.id, question.candidate_answer.trim()]),
    );
    const firstUnansweredQuestionIndex = questions.findIndex((question) => {
        const candidateAnswer = typeof question?.candidate_answer === 'string'
            ? question.candidate_answer.trim()
            : '';

        return candidateAnswer === '';
    });
    const allQuestionsAnswered = questions.length > 0 && firstUnansweredQuestionIndex === -1;

    let interviewCompleted = interviewCompletedFromServer || allQuestionsAnswered;
    let currentQuestionIndex = interviewCompleted
        ? questions.length
        : Math.max(firstUnansweredQuestionIndex, 0);
    let remainingSeconds = Math.max(answerTimeSeconds, 1);
    let timerId = null;

    let microphoneStream = null;
    let activeRecorder = null;
    let activeRecordingMode = null;
    let audioChunks = [];

    let transcribing = false;
    let submitting = false;
    let phraseCompleted = false;
    const submittedAnswers = new Map(initialSubmittedAnswers);

    const showScreen = (screenName) => {
        if (screenStart instanceof HTMLElement) {
            screenStart.classList.toggle('hidden', screenName !== 'start');
        }

        if (screenChat instanceof HTMLElement) {
            screenChat.classList.toggle('hidden', screenName !== 'chat');
        }

        if (screenInterview instanceof HTMLElement) {
            screenInterview.classList.toggle('hidden', screenName !== 'interview');
        }
    };

    const setMicrophoneStatus = (message, isError = false) => {
        if (! (microphoneStatusElement instanceof HTMLElement)) {
            return;
        }

        microphoneStatusElement.textContent = message;
        microphoneStatusElement.classList.toggle('text-red-600', isError);
        microphoneStatusElement.classList.toggle('text-[#4f556f]', ! isError);
    };

    const setAnswerStatus = (message, isError = false) => {
        if (! (answerStatusElement instanceof HTMLElement)) {
            return;
        }

        answerStatusElement.textContent = message;
        answerStatusElement.classList.toggle('text-red-600', isError);
        answerStatusElement.classList.toggle('text-[#545a78]', ! isError);
    };

    const formatTimer = (seconds) => {
        const safeSeconds = Math.max(seconds, 0);
        const minutes = Math.floor(safeSeconds / 60)
            .toString()
            .padStart(2, '0');
        const secs = (safeSeconds % 60).toString().padStart(2, '0');

        return `${minutes}:${secs}`;
    };

    const escapeHtml = (value) => value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const resolveFileExtension = (mimeType) => {
        const normalizedMimeType = mimeType.toLowerCase();

        if (normalizedMimeType.includes('ogg')) {
            return 'ogg';
        }

        if (normalizedMimeType.includes('wav')) {
            return 'wav';
        }

        if (normalizedMimeType.includes('mp4') || normalizedMimeType.includes('m4a')) {
            return 'm4a';
        }

        if (normalizedMimeType.includes('mpeg') || normalizedMimeType.includes('mp3')) {
            return 'mp3';
        }

        return 'webm';
    };

    const resolveRecorderMimeType = () => {
        if (! mediaRecorderSupported) {
            return '';
        }

        const candidates = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
            'audio/ogg',
            'audio/mp4',
            'audio/mpeg',
        ];

        if (typeof window.MediaRecorder.isTypeSupported !== 'function') {
            return candidates[0];
        }

        return candidates.find((candidate) => window.MediaRecorder.isTypeSupported(candidate)) ?? '';
    };

    const hasValidMicrophoneStream = () => {
        if (typeof window.MediaStream === 'undefined') {
            return false;
        }

        return microphoneStream instanceof window.MediaStream;
    };

    const ensureMicrophoneAccess = async () => {
        if (! recordingSupported) {
            setMicrophoneStatus('Запись звука недоступна в этом браузере.', true);

            return false;
        }

        if (hasValidMicrophoneStream()) {
            return true;
        }

        try {
            microphoneStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            setMicrophoneStatus('Микрофон доступен. Можно продолжать.');

            return true;
        } catch (error) {
            setMicrophoneStatus('Не удалось получить доступ к микрофону.', true);

            return false;
        }
    };

    const stopTimer = () => {
        if (timerId !== null) {
            window.clearInterval(timerId);
            timerId = null;
        }
    };

    const renderQuestions = () => {
        if (! (questionList instanceof HTMLElement)) {
            return;
        }

        const safeIndex = Math.max(currentQuestionIndex, 0);
        const visibleQuestionsCount = interviewCompleted
            ? questions.length
            : Math.min(safeIndex + 1, questions.length);

        const questionCardsHtml = questions
            .slice(0, visibleQuestionsCount)
            .map((question, index) => {
                const isCurrent = ! interviewCompleted && index === currentQuestionIndex;
                const answerText = submittedAnswers.get(question.id) ?? '';

                if (isCurrent) {
                    return `
                        <section class="space-y-3" id="current-question-card">
                            <div class="flex items-center gap-2 text-xs text-[#72789c]">
                                <span class="h-[1px] flex-1 bg-[#d5daf3]"></span>
                                <span>Вопрос ${index + 1} из ${questions.length}</span>
                            </div>
                            <article class="rounded-2xl bg-white px-8 py-10 shadow-[0_12px_36px_rgba(93,103,166,0.13)]">
                                <p class="text-center text-[28px] font-medium leading-tight text-[#202541]">${escapeHtml(question.text)}</p>
                                <p class="mt-8 text-center text-2xl font-medium text-[#2c3150]" id="current-question-timer">${formatTimer(remainingSeconds)}</p>
                            </article>
                        </section>
                    `;
                }

                return `
                    <section class="space-y-3">
                        <div class="flex items-center gap-2 text-xs text-[#72789c]">
                            <span class="h-[1px] flex-1 bg-[#d5daf3]"></span>
                            <span>Вопрос ${index + 1} из ${questions.length}</span>
                        </div>
                        <article class="rounded-2xl bg-white px-8 py-9 shadow-[0_12px_36px_rgba(93,103,166,0.13)]">
                            <p class="text-center text-xl font-medium leading-relaxed text-[#222744]">${escapeHtml(question.text)}</p>
                            <p class="mt-6 text-center text-sm font-medium text-[#289a5f]">✓ Сохранено</p>
                            ${answerText !== '' ? `<p class="mt-4 text-center text-xs text-[#7d84a5]">${escapeHtml(answerText)}</p>` : ''}
                        </article>
                    </section>
                `;
            })
            .join('');

        const completionCardHtml = interviewCompleted
            ? `
                <section id="interview-completed-card" class="space-y-3">
                    <article class="rounded-2xl rounded-tl-md bg-white px-8 py-8 shadow-[0_12px_36px_rgba(93,103,166,0.13)]">
                        <p class="text-center text-sm text-[#2f344d]">${escapeHtml(completionMessage)}</p>
                    </article>
                </section>
            `
            : '';

        questionList.innerHTML = `${questionCardsHtml}${completionCardHtml}`;

        const currentCard = document.getElementById('current-question-card');

        if (currentCard instanceof HTMLElement) {
            currentCard.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
            return;
        }

        const completedCard = document.getElementById('interview-completed-card');

        if (completedCard instanceof HTMLElement) {
            completedCard.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        }
    };

    const startQuestionTimer = () => {
        if (interviewCompleted || currentQuestionIndex >= questions.length) {
            stopTimer();
            return;
        }

        stopTimer();
        remainingSeconds = Math.max(answerTimeSeconds, 1);
        renderQuestions();

        timerId = window.setInterval(() => {
            remainingSeconds -= 1;
            const timerElement = document.getElementById('current-question-timer');

            if (timerElement instanceof HTMLElement) {
                timerElement.textContent = formatTimer(remainingSeconds);
            }

            if (remainingSeconds <= 0) {
                stopTimer();
                setAnswerStatus('Время на вопрос истекло. Запишите ответ или выберите "Не знаю ответ".', true);
            }
        }, 1000);
    };

    const syncActionButtons = () => {
        const isPhraseRecording = activeRecordingMode === 'phrase';
        const isAnswerRecording = activeRecordingMode === 'answer';
        const anyRecording = activeRecordingMode !== null;
        const interviewFinished = interviewCompleted || currentQuestionIndex >= questions.length;

        if (phraseRecordToggleButton instanceof HTMLButtonElement) {
            phraseRecordToggleButton.disabled = transcribing || submitting || isAnswerRecording;
            phraseRecordToggleButton.textContent = isPhraseRecording ? 'Остановить запись' : 'Записать фразу';
            phraseRecordToggleButton.className = isPhraseRecording
                ? 'inline-flex h-11 items-center justify-center rounded-full bg-[#eb1f3a] px-7 text-sm font-semibold text-white shadow-[0_6px_0_rgba(160,28,45,0.45)] transition hover:bg-[#d61731]'
                : 'inline-flex h-11 items-center justify-center rounded-full bg-[#1b045f] px-7 text-sm font-semibold text-white shadow-[0_6px_0_rgba(112,102,189,0.55)] transition hover:bg-[#250875]';
        }

        if (recordToggleButton instanceof HTMLButtonElement) {
            recordToggleButton.disabled = transcribing || submitting || isPhraseRecording || interviewFinished;
            recordToggleButton.textContent = isAnswerRecording ? 'Остановить запись' : 'Записать ответ';
            recordToggleButton.className = isAnswerRecording
                ? 'inline-flex h-14 min-w-[280px] items-center justify-center rounded-full bg-[#eb1f3a] px-8 text-sm font-semibold text-white shadow-[0_6px_0_rgba(160,28,45,0.45)] transition hover:bg-[#d61731]'
                : 'inline-flex h-14 min-w-[280px] items-center justify-center rounded-full bg-[#1b045f] px-8 text-sm font-semibold text-white shadow-[0_6px_0_rgba(112,102,189,0.55)] transition hover:bg-[#250875]';
        }

        if (skipAnswerButton instanceof HTMLButtonElement) {
            skipAnswerButton.disabled = transcribing || submitting || anyRecording || interviewFinished;
            skipAnswerButton.classList.toggle('opacity-40', skipAnswerButton.disabled);
            skipAnswerButton.classList.toggle('cursor-not-allowed', skipAnswerButton.disabled);
        }
    };

    const requestTranscription = async (audioBlob) => {
        if (transcribeEndpoint === '') {
            throw new Error('Маршрут транскрибации не настроен.');
        }

        if (csrfToken === undefined || csrfToken === null || csrfToken === '') {
            throw new Error('Не найден CSRF токен. Обновите страницу.');
        }

        const extension = resolveFileExtension(audioBlob.type || '');
        const formData = new FormData();
        formData.append('audio', audioBlob, `recording.${extension}`);
        formData.append('language', 'auto');

        const response = await window.fetch(transcribeEndpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
        });

        const payload = await response.json().catch(() => null);

        if (! response.ok) {
            const message = payload?.errors?.audio?.[0]
                ?? payload?.errors?.language?.[0]
                ?? payload?.message
                ?? 'Не удалось распознать аудио.';

            throw new Error(message);
        }

        if (! payload || typeof payload.text !== 'string') {
            throw new Error('Некорректный ответ сервера распознавания.');
        }

        return payload.text.trim();
    };

    const submitAnswer = async (candidateAnswer) => {
        const question = questions[currentQuestionIndex];

        if (! question || submitting) {
            return;
        }

        if (csrfToken === undefined || csrfToken === null || csrfToken === '') {
            setAnswerStatus('Не найден CSRF токен. Обновите страницу.', true);
            return;
        }

        submitting = true;
        syncActionButtons();
        setAnswerStatus('Сохраняем ответ...');
        stopTimer();

        try {
            const endpoint = answerEndpointTemplate.replace('__QUESTION_ID__', String(question.id));
            const response = await window.fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    candidate_answer: candidateAnswer,
                }),
            });

            const payload = await response.json();

            if (! response.ok) {
                const message = payload?.errors?.candidate_answer?.[0]
                    ?? payload?.message
                    ?? 'Не удалось сохранить ответ.';

                setAnswerStatus(message, true);
                startQuestionTimer();
                return;
            }

            submittedAnswers.set(question.id, candidateAnswer);

            if (payload.completed === true) {
                interviewCompleted = true;
                currentQuestionIndex = questions.length;
                renderQuestions();
                setAnswerStatus(
                    typeof payload.message === 'string' && payload.message.trim() !== ''
                        ? payload.message.trim()
                        : completionMessage,
                );
                return;
            }

            if (payload.next_question?.id) {
                const nextIndex = questions.findIndex((item) => Number(item.id) === Number(payload.next_question.id));
                currentQuestionIndex = nextIndex >= 0 ? nextIndex : currentQuestionIndex + 1;
            } else {
                currentQuestionIndex += 1;
            }

            setAnswerStatus('Ответ сохранен.');
            startQuestionTimer();
        } catch (error) {
            setAnswerStatus('Ошибка сети при сохранении ответа.', true);
            startQuestionTimer();
        } finally {
            submitting = false;
            syncActionButtons();
        }
    };

    const processRecordedAudio = async (audioBlob, mode) => {
        transcribing = true;
        syncActionButtons();

        if (mode === 'phrase') {
            setMicrophoneStatus('Распознаю тестовую фразу...');
        } else {
            setAnswerStatus('Распознаю ответ...');
        }

        try {
            const transcript = await requestTranscription(audioBlob);

            if (mode === 'phrase') {
                if (phraseResultElement instanceof HTMLElement) {
                    phraseResultElement.textContent = transcript === '' ? 'Не удалось распознать фразу.' : transcript;
                }

                if (phraseUserRow instanceof HTMLElement) {
                    phraseUserRow.classList.remove('hidden');
                    phraseUserRow.classList.add('flex');
                }

                if (phraseSuccessRow instanceof HTMLElement) {
                    phraseSuccessRow.classList.remove('hidden');
                }

                if (chatContinueButton instanceof HTMLButtonElement) {
                    chatContinueButton.classList.remove('hidden');
                    chatContinueButton.classList.add('inline-flex');
                }

                phraseCompleted = true;
                setMicrophoneStatus(transcript === '' ? 'Фраза не распознана, попробуйте снова.' : 'Отлично, все работает.');
            } else {
                const normalizedAnswer = transcript === '' ? 'Не знаю ответ' : transcript;
                await submitAnswer(normalizedAnswer);
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Не удалось обработать аудио.';

            if (mode === 'phrase') {
                setMicrophoneStatus(message, true);
            } else {
                setAnswerStatus(message, true);
            }
        } finally {
            transcribing = false;
            syncActionButtons();
        }
    };

    const stopRecording = () => {
        if (activeRecorder instanceof MediaRecorder && activeRecorder.state !== 'inactive') {
            activeRecorder.stop();
        }
    };

    const startRecording = async (mode) => {
        if (submitting || transcribing || activeRecordingMode !== null || interviewCompleted) {
            return;
        }

        if (! recordingSupported) {
            if (speechFallbackElement instanceof HTMLElement) {
                speechFallbackElement.classList.remove('hidden');
            }

            if (mode === 'answer') {
                const fallbackAnswer = window.prompt('Запись недоступна. Введите ответ вручную:') ?? '';

                if (fallbackAnswer.trim() !== '') {
                    await submitAnswer(fallbackAnswer.trim());
                }
            } else {
                setMicrophoneStatus('Запись недоступна в этом браузере.', true);
            }

            return;
        }

        const hasAccess = await ensureMicrophoneAccess();

        if (! hasAccess || ! hasValidMicrophoneStream()) {
            return;
        }

        const recorderMimeType = resolveRecorderMimeType();

        try {
            activeRecorder = recorderMimeType === ''
                ? new window.MediaRecorder(microphoneStream)
                : new window.MediaRecorder(microphoneStream, { mimeType: recorderMimeType });
        } catch (error) {
            if (mode === 'phrase') {
                setMicrophoneStatus('Не удалось начать запись.', true);
            } else {
                setAnswerStatus('Не удалось начать запись ответа.', true);
            }

            return;
        }

        if (! (activeRecorder instanceof MediaRecorder)) {
            return;
        }

        audioChunks = [];
        activeRecordingMode = mode;
        syncActionButtons();

        if (mode === 'phrase') {
            setMicrophoneStatus('Идет запись фразы...');
        } else {
            setAnswerStatus('Идет запись ответа...');
        }

        activeRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        };

        activeRecorder.onerror = () => {
            if (mode === 'phrase') {
                setMicrophoneStatus('Ошибка записи фразы.', true);
            } else {
                setAnswerStatus('Ошибка записи ответа.', true);
            }
        };

        activeRecorder.onstop = () => {
            const stoppedMode = activeRecordingMode;
            activeRecordingMode = null;

            const blobMimeType = audioChunks[0]?.type || recorderMimeType || 'audio/webm';
            const audioBlob = new Blob(audioChunks, { type: blobMimeType });
            audioChunks = [];
            activeRecorder = null;
            syncActionButtons();

            if (audioBlob.size === 0) {
                if (stoppedMode === 'phrase') {
                    setMicrophoneStatus('Пустая запись. Попробуйте еще раз.', true);
                } else {
                    setAnswerStatus('Пустая запись. Нажмите "Записать ответ" и повторите.', true);
                }

                return;
            }

            void processRecordedAudio(audioBlob, stoppedMode ?? mode);
        };

        activeRecorder.start();
    };

    if (speechFallbackElement instanceof HTMLElement && ! recordingSupported) {
        speechFallbackElement.classList.remove('hidden');
    }

    if (startFlowButton instanceof HTMLButtonElement) {
        startFlowButton.addEventListener('click', () => {
            showScreen('chat');
        });
    }

    if (microphoneAccessButton instanceof HTMLButtonElement) {
        microphoneAccessButton.addEventListener('click', () => {
            void ensureMicrophoneAccess();
        });
    }

    if (phraseRecordToggleButton instanceof HTMLButtonElement) {
        phraseRecordToggleButton.addEventListener('click', () => {
            if (activeRecordingMode === 'phrase') {
                stopRecording();
                return;
            }

            void startRecording('phrase');
        });
    }

    if (chatContinueButton instanceof HTMLButtonElement) {
        chatContinueButton.addEventListener('click', () => {
            if (! phraseCompleted) {
                setMicrophoneStatus('Сначала запишите тестовую фразу.', true);
                return;
            }

            if (instructionsModal instanceof HTMLElement) {
                instructionsModal.classList.remove('hidden');
                instructionsModal.classList.add('flex');
            }
        });
    }

    if (instructionsStartButton instanceof HTMLButtonElement) {
        instructionsStartButton.addEventListener('click', () => {
            if (instructionsModal instanceof HTMLElement) {
                instructionsModal.classList.add('hidden');
                instructionsModal.classList.remove('flex');
            }

            showScreen('interview');
            startQuestionTimer();
            syncActionButtons();
        });
    }

    if (recordToggleButton instanceof HTMLButtonElement) {
        recordToggleButton.addEventListener('click', () => {
            if (activeRecordingMode === 'answer') {
                stopRecording();
                return;
            }

            void startRecording('answer');
        });
    }

    if (skipAnswerButton instanceof HTMLButtonElement) {
        skipAnswerButton.addEventListener('click', () => {
            void submitAnswer('Не знаю ответ');
        });
    }

    renderQuestions();
    syncActionButtons();

    if (interviewCompleted) {
        showScreen('interview');
        setAnswerStatus(completionMessage);
    } else {
        showScreen('start');
    }
}
