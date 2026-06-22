<script setup>
const props = defineProps({
  modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const { moment, toApiDate } = useJalaliDate()

const viewMonth = ref(moment().startOf('jMonth'))

const weekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']

const monthLabel = computed(() => viewMonth.value.format('jMMMM jYYYY'))

watch(() => props.modelValue, val => {
  if (val)
    viewMonth.value = moment(val).startOf('jMonth')
}, { immediate: true })

const monthDays = computed(() => {
  const monthStart = viewMonth.value.clone().startOf('jMonth')
  const daysInMonth = moment.jDaysInMonth(monthStart.jYear(), monthStart.jMonth())
  const startOffset = (monthStart.day() + 1) % 7
  const cells = []

  for (let i = 0; i < startOffset; i++)
    cells.push(null)

  for (let d = 1; d <= daysInMonth; d++) {
    const dayMoment = monthStart.clone().jDate(d)
    const gregorian = toApiDate(dayMoment)

    cells.push({
      jDate: dayMoment.format('jD'),
      gregorian,
      isToday: dayMoment.isSame(moment(), 'day'),
      isSelected: toApiDate(props.modelValue) === gregorian,
    })
  }

  return cells
})

const prevMonth = () => {
  viewMonth.value = viewMonth.value.clone().subtract(1, 'jMonth')
}

const nextMonth = () => {
  viewMonth.value = viewMonth.value.clone().add(1, 'jMonth')
}

const selectDay = gregorian => {
  emit('update:modelValue', gregorian)
}

const goToday = () => {
  const today = toApiDate(moment())
  viewMonth.value = moment().startOf('jMonth')
  emit('update:modelValue', today)
}
</script>

<template>
  <div class="jalali-mini-calendar">
    <div class="jalali-mini-calendar__header">
      <IconBtn
        size="small"
        @click="prevMonth"
      >
        <VIcon
          icon="tabler-chevron-right"
          size="20"
        />
      </IconBtn>

      <span class="jalali-mini-calendar__title">{{ monthLabel }}</span>

      <IconBtn
        size="small"
        @click="nextMonth"
      >
        <VIcon
          icon="tabler-chevron-left"
          size="20"
        />
      </IconBtn>
    </div>

    <div class="jalali-mini-calendar__weekdays">
      <span
        v-for="day in weekDays"
        :key="day"
        class="jalali-mini-calendar__weekday"
      >
        {{ day }}
      </span>
    </div>

    <div class="jalali-mini-calendar__grid">
      <template
        v-for="(cell, index) in monthDays"
        :key="index"
      >
        <span
          v-if="!cell"
          class="jalali-mini-calendar__cell jalali-mini-calendar__cell--empty"
        />
        <button
          v-else
          type="button"
          class="jalali-mini-calendar__cell"
          :class="{
            'jalali-mini-calendar__cell--today': cell.isToday,
            'jalali-mini-calendar__cell--selected': cell.isSelected,
          }"
          @click="selectDay(cell.gregorian)"
        >
          {{ cell.jDate }}
        </button>
      </template>
    </div>

    <VBtn
      block
      variant="tonal"
      size="small"
      class="mt-3"
      @click="goToday"
    >
      امروز
    </VBtn>
  </div>
</template>

<style lang="scss" scoped>
.jalali-mini-calendar {
  inline-size: 100%;
  padding-block: 0.5rem;
}

.jalali-mini-calendar__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-block-end: 0.75rem;
}

.jalali-mini-calendar__title {
  font-size: 1rem;
  font-weight: 600;
}

.jalali-mini-calendar__weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  margin-block-end: 6px;
}

.jalali-mini-calendar__weekday {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  font-weight: 600;
  text-align: center;
}

.jalali-mini-calendar__grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.jalali-mini-calendar__cell {
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 8px;
  background: transparent;
  block-size: 2.25rem;
  color: inherit;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 500;
  transition: background 0.15s;

  &--empty {
    cursor: default;
  }

  &:not(&--empty):hover {
    background: rgba(var(--v-theme-on-surface), var(--v-hover-opacity));
  }

  &--today {
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
  }

  &--selected {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    font-weight: 700;

    &:hover {
      background: rgb(var(--v-theme-primary));
    }
  }
}
</style>
