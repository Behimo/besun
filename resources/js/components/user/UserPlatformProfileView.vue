<script setup>
const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  showStats: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['submitReview'])

const reviewForm = defineModel('reviewForm', {
  type: Object,
  default: () => ({ rating: 5, comment: '', is_public: true }),
})

const reviewLoading = defineModel('reviewLoading', {
  type: Boolean,
  default: false,
})

const reviewError = defineModel('reviewError', {
  type: String,
  default: '',
})
</script>

<template>
  <div>
    <div class="d-flex align-center gap-4 mb-6 flex-wrap">
      <VAvatar
        size="72"
        color="primary"
        variant="tonal"
      >
        <VImg
          v-if="data.user?.avatar"
          :src="data.user.avatar"
        />
        <span v-else>{{ data.user?.name?.charAt(0) }}</span>
      </VAvatar>
      <div class="flex-grow-1">
        <h5 class="text-h5 mb-1">
          {{ data.user?.name }}
        </h5>
        <div
          class="text-body-2"
          dir="ltr"
        >
          {{ data.user?.phone }}
        </div>
        <div class="text-caption text-medium-emphasis">
          عضو سیستم از {{ data.user?.member_since }}
        </div>
      </div>
      <div
        v-if="showStats && (data.stats || data.user?.average_rating !== undefined)"
        class="d-flex flex-wrap gap-2"
      >
        <VChip
          v-if="data.stats?.tenant_count ?? data.user?.tenant_count"
          size="small"
          prepend-icon="tabler-building"
        >
          {{ data.stats?.tenant_count ?? data.user?.tenant_count }} مجموعه
        </VChip>
        <VChip
          v-if="data.stats?.average_rating ?? data.user?.average_rating"
          size="small"
          color="warning"
          prepend-icon="tabler-star-filled"
        >
          {{ data.stats?.average_rating ?? data.user?.average_rating }}
        </VChip>
        <VChip
          v-if="data.stats?.completion_percent !== undefined"
          size="small"
          :color="data.stats.completion_percent >= 80 ? 'success' : 'info'"
        >
          تکمیل {{ data.stats.completion_percent }}٪
        </VChip>
      </div>
    </div>

    <VCard
      v-if="data.profile && !data.profile.hidden && (data.profile.job_title || data.profile.city || data.profile.bio || data.profile.skills?.length)"
      variant="tonal"
      class="mb-4"
    >
      <VCardText>
        <div
          v-if="data.profile.job_title"
          class="mb-2"
        >
          <strong>عنوان شغلی:</strong> {{ data.profile.job_title }}
        </div>
        <div
          v-if="data.profile.city"
          class="mb-2"
        >
          <strong>شهر:</strong> {{ data.profile.city }}
        </div>
        <div
          v-if="data.profile.bio"
          class="mb-2"
        >
          <strong>درباره:</strong> {{ data.profile.bio }}
        </div>
        <div
          v-if="data.profile.skills?.length"
          class="d-flex flex-wrap gap-2"
        >
          <VChip
            v-for="skill in data.profile.skills"
            :key="skill"
            size="small"
          >
            {{ skill }}
          </VChip>
        </div>
      </VCardText>
    </VCard>

    <VAlert
      v-else-if="data.profile?.hidden"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      این کاربر پروفایل خود را خصوصی کرده است.
    </VAlert>

    <h6 class="text-subtitle-1 mb-3">
      سابقه همکاری در مجموعه‌ها
    </h6>
    <VTable
      v-if="data.tenant_history?.length"
      density="compact"
      class="mb-6"
    >
      <thead>
        <tr>
          <th>مجموعه</th>
          <th>نقش</th>
          <th>شروع</th>
          <th>پایان</th>
          <th>وضعیت</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in data.tenant_history"
          :key="row.tenant_id"
        >
          <td>{{ row.tenant_name }}</td>
          <td>{{ row.role }}</td>
          <td>{{ row.joined_at || '—' }}</td>
          <td>{{ row.left_at || '—' }}</td>
          <td>
            <VChip
              size="x-small"
              :color="row.is_active ? 'success' : 'secondary'"
            >
              {{ row.is_active ? 'فعال' : 'پایان یافته' }}
            </VChip>
          </td>
        </tr>
      </tbody>
    </VTable>
    <VAlert
      v-else
      type="info"
      variant="tonal"
      class="mb-6"
    >
      سابقه همکاری ثبت نشده است.
    </VAlert>

    <h6 class="text-subtitle-1 mb-3">
      نظر کارفرمایان
    </h6>
    <div
      v-if="data.reviews?.length"
      class="mb-6"
    >
      <VCard
        v-for="review in data.reviews"
        :key="review.id"
        variant="outlined"
        class="mb-3"
      >
        <VCardText>
          <div class="d-flex justify-space-between align-center mb-2">
            <strong>{{ review.tenant_name }}</strong>
            <VRating
              :model-value="review.rating"
              readonly
              density="compact"
              size="small"
            />
          </div>
          <div class="text-caption text-medium-emphasis mb-2">
            {{ review.reviewer_name }} — {{ review.role_at_review }} — {{ review.created_at }}
          </div>
          <p
            v-if="review.comment"
            class="mb-0"
          >
            {{ review.comment }}
          </p>
        </VCardText>
      </VCard>
    </div>
    <VAlert
      v-else
      type="info"
      variant="tonal"
      class="mb-6"
    >
      هنوز نظری ثبت نشده است.
    </VAlert>

    <template v-if="data.viewer_context?.can_review">
      <VDivider class="mb-4" />
      <h6 class="text-subtitle-1 mb-3">
        ثبت نظر کارفرما (مجموعه شما)
      </h6>
      <VAlert
        v-if="reviewError"
        type="error"
        variant="tonal"
        class="mb-4"
      >
        {{ reviewError }}
      </VAlert>
      <VRating
        v-model="reviewForm.rating"
        class="mb-4"
      />
      <AppTextarea
        v-model="reviewForm.comment"
        label="نظر شما"
        rows="3"
        class="mb-4"
      />
      <VCheckbox
        v-model="reviewForm.is_public"
        label="نمایش عمومی برای سایر کارفرماها"
        class="mb-4"
      />
      <VBtn
        color="primary"
        :loading="reviewLoading"
        @click="emit('submitReview')"
      >
        {{ data.viewer_context.existing_review ? 'به‌روزرسانی نظر' : 'ثبت نظر' }}
      </VBtn>
    </template>
  </div>
</template>
