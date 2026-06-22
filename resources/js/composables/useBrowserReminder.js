export const useBrowserReminder = () => {
  const permissionGranted = ref(false)

  const requestPermission = async () => {
    if (!('Notification' in window))
      return false

    if (Notification.permission === 'granted') {
      permissionGranted.value = true

      return true
    }

    if (Notification.permission === 'denied')
      return false

    const result = await Notification.requestPermission()

    permissionGranted.value = result === 'granted'

    return permissionGranted.value
  }

  const showBrowserNotification = (item, { onClick } = {}) => {
    if (!permissionGranted.value && Notification.permission !== 'granted')
      return

    if (Notification.permission !== 'granted')
      return

    const n = new Notification(item.title || 'یادآوری', {
      body: item.subtitle || '',
      tag: item.id,
      dir: 'rtl',
    })

    n.onclick = () => {
      window.focus()
      onClick?.(item)
      n.close()
    }
  }

  return {
    permissionGranted,
    requestPermission,
    showBrowserNotification,
  }
}
