<script setup>
const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  title: {
    type: String,
    default: 'نرخ ریزش بین مراحل',
  },
})

const maxDropoff = computed(() =>
  Math.max(...props.items.map(i => i.dropoff_rate ?? 0), 0))

const dropoffColor = rate => {
  if (rate >= maxDropoff.value * 0.8 && rate > 0)
    return 'error'

  if (rate >= maxDropoff.value * 0.5)
    return 'warning'

  return 'success'
}
</script>

<template>
  <VCard :title="title">
    <VCardText>
      <div
        v-if="!items.length"
        class="text-medium-emphasis text-center py-8"
      >
        حداقل دو مرحله برای محاسبه ریزش لازم است.
      </div>
      <VTable v-else>
        <thead>
          <tr>
            <th>از مرحله</th>
            <th>به مرحله</th>
            <th>ورودی</th>
            <th>پیشرفت</th>
            <th>از دست رفته</th>
            <th>مانده در مرحله</th>
            <th>نرخ تبدیل</th>
            <th>نرخ ریزش</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, idx) in items"
            :key="idx"
          >
            <td>
              <VChip
                size="x-small"
                :color="row.from_color"
                variant="flat"
              >
                {{ row.from_stage }}
              </VChip>
            </td>
            <td>
              <VChip
                size="x-small"
                :color="row.to_color"
                variant="flat"
              >
                {{ row.to_stage }}
              </VChip>
            </td>
            <td>{{ row.entered?.toLocaleString('fa-IR') ?? 0 }}</td>
            <td>{{ row.progressed?.toLocaleString('fa-IR') ?? 0 }}</td>
            <td>{{ row.lost?.toLocaleString('fa-IR') ?? 0 }}</td>
            <td>{{ row.still_in_stage?.toLocaleString('fa-IR') ?? 0 }}</td>
            <td>{{ row.conversion_rate }}٪</td>
            <td>
              <VChip
                size="small"
                :color="dropoffColor(row.dropoff_rate)"
                variant="tonal"
              >
                {{ row.dropoff_rate }}٪
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
      <p
        v-if="items.length"
        class="text-caption text-medium-emphasis mt-4 mb-0"
      >
        نرخ ریزش از تاریخچه جابجایی مراحل محاسبه می‌شود. با جابجایی کارت‌ها در کاریز، داده دقیق‌تر می‌شود.
      </p>
    </VCardText>
  </VCard>
</template>
