<script setup>
import { getAparatEmbedUrl, getVideoMediaType, getYoutubeEmbedUrl } from '@/data/page-tutorials'

const {
  tutorial,
  hasTutorial,
  isExpanded,
  showFab,
  isPermanentlyDismissed,
  closePanel,
  openPanel,
  dismissPermanently,
} = usePageTutorial()

const dontShowAgain = ref(false)

const mediaType = computed(() => getVideoMediaType(tutorial.value?.videoUrl))

const embedUrl = computed(() => {
  const url = tutorial.value?.videoUrl
  if (!url)
    return null

  if (mediaType.value === 'aparat')
    return getAparatEmbedUrl(url)

  if (mediaType.value === 'youtube')
    return getYoutubeEmbedUrl(url)

  return null
})

const handleClose = () => {
  if (dontShowAgain.value)
    dismissPermanently()
  else
    closePanel()

  dontShowAgain.value = false
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="hasTutorial && tutorial"
      class="page-tutorial-root"
    >
      <Transition name="tutorial-slide">
        <div
          v-if="isExpanded"
          class="page-tutorial-panel"
        >
          <div class="page-tutorial-panel__header">
            <div class="d-flex align-center gap-2">
              <VAvatar
                color="primary"
                variant="tonal"
                size="36"
                rounded
              >
                <VIcon
                  icon="tabler-school"
                  size="20"
                />
              </VAvatar>
              <div>
                <p class="text-body-2 font-weight-semibold mb-0">
                  {{ tutorial.title }}
                </p>
                <p class="text-caption text-medium-emphasis mb-0">
                  راهنمای این صفحه
                </p>
              </div>
            </div>

            <IconBtn
              size="small"
              @click="handleClose"
            >
              <VIcon
                icon="tabler-x"
                size="18"
              />
            </IconBtn>
          </div>

          <div class="page-tutorial-panel__media">
            <iframe
              v-if="embedUrl"
              :src="embedUrl"
              title="ویدئو آموزشی"
              allowfullscreen
            />
            <video
              v-else-if="mediaType === 'video' && tutorial.videoUrl"
              :src="tutorial.videoUrl"
              :poster="tutorial.posterUrl"
              controls
              playsinline
              preload="metadata"
            />
            <div
              v-else-if="tutorial.posterUrl"
              class="page-tutorial-panel__poster"
            >
              <img
                :src="tutorial.posterUrl"
                :alt="tutorial.title"
              >
              <div class="page-tutorial-panel__poster-overlay">
                <VIcon
                  icon="tabler-player-play"
                  size="32"
                />
                <span class="text-caption">ویدئو به‌زودی اضافه می‌شود</span>
              </div>
            </div>
            <div
              v-else
              class="page-tutorial-panel__empty"
            >
              <VIcon
                icon="tabler-video-off"
                size="40"
                class="mb-2"
              />
              <span class="text-caption">ویدئوی آموزشی ثبت نشده است</span>
            </div>
          </div>

          <p
            v-if="tutorial.description"
            class="page-tutorial-panel__desc text-body-2 text-medium-emphasis"
          >
            {{ tutorial.description }}
          </p>

          <div class="page-tutorial-panel__footer">
            <VCheckbox
              v-model="dontShowAgain"
              label="دیگر نمایش نده"
              density="compact"
              hide-details
              color="primary"
            />
            <VBtn
              size="small"
              variant="tonal"
              color="secondary"
              @click="handleClose"
            >
              بستن
            </VBtn>
          </div>
        </div>
      </Transition>

      <Transition name="tutorial-fab">
        <VBtn
          v-if="showFab"
          class="page-tutorial-fab"
          :color="isPermanentlyDismissed ? 'secondary' : 'primary'"
          :variant="isPermanentlyDismissed ? 'tonal' : 'elevated'"
          icon
          size="large"
          @click="openPanel"
        >
          <VIcon icon="tabler-help-circle" />
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ isPermanentlyDismissed ? 'مشاهده راهنما' : 'راهنمای این صفحه' }}
          </VTooltip>
        </VBtn>
      </Transition>
    </div>
  </Teleport>
</template>

<style lang="scss" scoped>
.page-tutorial-root {
  position: fixed;
  z-index: 1005;
  inset-block-end: 1.5rem;
  inset-inline-end: 1.5rem;
  pointer-events: none;

  > * {
    pointer-events: auto;
  }
}

.page-tutorial-panel {
  overflow: hidden;
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  box-shadow:
    0 8px 28px rgba(var(--v-shadow-key-umbra-opacity), 0.14),
    0 2px 8px rgba(var(--v-shadow-key-umbra-opacity), 0.08);
  inline-size: min(22rem, calc(100vw - 2rem));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));

  &__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.875rem 1rem 0.625rem;
  }

  &__media {
    position: relative;
    aspect-ratio: 16 / 9;
    background: rgba(var(--v-theme-on-surface), 0.04);

    iframe,
    video {
      display: block;
      border: 0;
      block-size: 100%;
      inline-size: 100%;
      object-fit: cover;
    }
  }

  &__poster {
    position: relative;
    block-size: 100%;

    img {
      display: block;
      block-size: 100%;
      inline-size: 100%;
      object-fit: cover;
    }
  }

  &__poster-overlay,
  &__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    text-align: center;
    padding-inline: 1rem;
  }

  &__poster-overlay {
    position: absolute;
    inset: 0;
    color: #fff;
    background: linear-gradient(
      to top,
      rgba(0, 0, 0, 0.55),
      rgba(0, 0, 0, 0.15)
    );
  }

  &__empty {
    block-size: 100%;
    color: rgba(var(--v-theme-on-surface), 0.5);
  }

  &__desc {
    margin: 0;
    padding: 0.75rem 1rem 0.25rem;
    line-height: 1.65;
  }

  &__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem 0.75rem;
  }
}

.page-tutorial-fab {
  box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.35);
}

.tutorial-slide-enter-active,
.tutorial-slide-leave-active {
  transition:
    opacity 0.28s ease,
    transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.tutorial-slide-enter-from,
.tutorial-slide-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.97);
}

.tutorial-fab-enter-active,
.tutorial-fab-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}

.tutorial-fab-enter-from,
.tutorial-fab-leave-to {
  opacity: 0;
  transform: scale(0.85);
}

@media (max-width: 600px) {
  .page-tutorial-root {
    inset-block-end: 1rem;
    inset-inline-end: 1rem;
  }

  .page-tutorial-panel {
    inline-size: min(20rem, calc(100vw - 1.5rem));
  }
}
</style>
