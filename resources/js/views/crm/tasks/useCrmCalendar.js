import faLocale from '@fullcalendar/core/locales/fa'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import timeGridPlugin from '@fullcalendar/timegrid'
import moment from 'moment-jalaali'
import { useConfigStore } from '@core/stores/config'

moment.loadPersian({ dialect: 'persian-modern', usePersianDigits: true })

export const CRM_CALENDAR_FILTERS = [
  { color: 'error', label: 'تسک فوری', category: 'Personal' },
  { color: 'warning', label: 'تسک عادی', category: 'Family' },
  { color: 'success', label: 'تسک انجام‌شده', category: 'Holiday' },
  { color: 'primary', label: 'تماس / فعالیت', category: 'Business' },
  { color: 'info', label: 'جلسه / یادداشت', category: 'ETC' },
  { color: 'info', label: 'یادآوری تسک', category: 'TaskReminder' },
  { color: 'secondary', label: 'پیگیری لید', category: 'LeadFollowUp' },
  { color: 'primary', label: 'پیگیری معامله', category: 'DealFollowUp' },
]

const calendarsColor = {
  Business: 'primary',
  Holiday: 'success',
  Personal: 'error',
  Family: 'warning',
  ETC: 'info',
  TaskReminder: 'info',
  LeadFollowUp: 'secondary',
  DealFollowUp: 'primary',
}

export function useCrmCalendar({ onTaskClick, onCreateTask, isLeftSidebarOpen }) {
  const configStore = useConfigStore()
  const refCalendar = ref()
  const calendarApi = ref(null)
  const selectedCategories = ref(CRM_CALENDAR_FILTERS.map(f => f.category))

  const fetchEvents = (info, successCallback, failureCallback) => {
    if (!info)
      return

    $api('/calendar/events', {
      query: {
        from: info.startStr.slice(0, 10),
        to: info.endStr.slice(0, 10),
      },
    })
      .then(res => {
        const events = (res.events ?? [])
          .filter(e => selectedCategories.value.includes(e.extendedProps?.calendar))
          .map(e => ({
            ...e,
            start: new Date(e.start),
            end: new Date(e.end),
          }))

        successCallback(events)
      })
      .catch(err => {
        console.error('calendar events', err)
        failureCallback?.(err)
      })
  }

  const refetchEvents = () => {
    calendarApi.value?.refetchEvents()
  }

  watch(selectedCategories, refetchEvents, { deep: true })

  const updateJalaliTitle = () => {
    nextTick(() => {
      const root = refCalendar.value?.$el
      const titleEl = root?.querySelector('.fc-toolbar-title')

      if (!titleEl || !calendarApi.value)
        return

      const current = calendarApi.value.getDate()

      titleEl.textContent = moment(current).format('jMMMM jYYYY')
    })
  }

  const calendarOptions = {
    plugins: [dayGridPlugin, interactionPlugin, timeGridPlugin, listPlugin],
    locale: faLocale,
    direction: configStore.isAppRTL ? 'rtl' : 'ltr',
    initialView: 'dayGridMonth',
    headerToolbar: {
      start: 'drawerToggler,prev,next title',
      end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
    },
    buttonText: {
      today: 'امروز',
      month: 'ماه',
      week: 'هفته',
      day: 'روز',
      list: 'لیست',
    },
    events: fetchEvents,
    forceEventDuration: true,
    editable: false,
    dayMaxEvents: 4,
    navLinks: true,
    expandRows: true,
    fixedWeekCount: false,
    dayCellContent(arg) {
      const jDay = moment(arg.date).format('jD')
      const isToday = moment(arg.date).isSame(moment(), 'day')

      return {
        html: `<span class="fc-jalali-day${isToday ? ' fc-jalali-day--today' : ''}">${jDay}</span>`,
      }
    },
    dayHeaderContent(arg) {
      return { html: moment(arg.date).format('dddd') }
    },
    listDayFormat(arg) {
      return moment(arg.date).format('dddd jD jMMMM jYYYY')
    },
    datesSet: () => updateJalaliTitle(),
    eventTimeFormat: {
      hour: '2-digit',
      minute: '2-digit',
      meridiem: false,
      hour12: false,
    },
    eventClassNames({ event: calendarEvent }) {
      const colorName = calendarsColor[calendarEvent.extendedProps.calendar]

      if (!colorName)
        return []

      return [`bg-light-${colorName} text-${colorName}`]
    },
    eventClick({ event: clickedEvent, jsEvent }) {
      jsEvent.preventDefault()

      const { type, task_id } = clickedEvent.extendedProps

      if (type === 'task' && task_id)
        onTaskClick?.(task_id)
    },
    dateClick(info) {
      onCreateTask?.(info.date)
    },
    customButtons: {
      drawerToggler: {
        text: 'calendarDrawerToggler',
        click() {
          isLeftSidebarOpen.value = true
        },
      },
    },
  }

  onMounted(() => {
    nextTick(() => {
      if (refCalendar.value) {
        calendarApi.value = refCalendar.value.getApi()
        updateJalaliTitle()
      }
    })
  })

  watch(() => configStore.isAppRTL, val => {
    calendarApi.value?.setOption('direction', val ? 'rtl' : 'ltr')
  }, { immediate: true })

  const jumpToDate = currentDate => {
    calendarApi.value?.gotoDate(new Date(currentDate))
  }

  const checkAll = computed({
    get: () => selectedCategories.value.length === CRM_CALENDAR_FILTERS.length,
    set: val => {
      if (val)
        selectedCategories.value = CRM_CALENDAR_FILTERS.map(f => f.category)
      else if (selectedCategories.value.length === CRM_CALENDAR_FILTERS.length)
        selectedCategories.value = []
    },
  })

  return {
    refCalendar,
    calendarOptions,
    refetchEvents,
    jumpToDate,
    selectedCategories,
    checkAll,
  }
}
