document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  const calendars = document.querySelectorAll('.my-custom-styled-calendar.holiday-calendar');

  calendars.forEach(cal => {
    const instance = cal.dataset.instance;
    let currentYear = parseInt(cal.dataset.year);
    let currentMonth = parseInt(cal.dataset.month) - 1; // JS 0-index
    
    const holidays = JSON.parse(cal.dataset.holidays || '[]');
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    const titleEl = cal.querySelector('.cal-title');
    const tbody = cal.querySelector('.cal-table tbody');
    const prevBtn = cal.querySelector('.cal-prev');
    const nextBtn = cal.querySelector('.cal-next');

function generateDateTable(year, month) {
  const days = [];
  const date = new Date(year, month, 1);

  while (date.getMonth() === month) {
    days.push({
      date: date.toISOString().split("T")[0],
      weekday: date.getDay(),
      day: date.getDate()
    });
    date.setDate(date.getDate() + 1);
  }

  return days;
}

function render() {
      const date = new Date(currentYear, currentMonth, 1);
      const year = date.getFullYear();
      const month = date.getMonth();
      const today = new Date();
      const isCurrent = year === today.getFullYear() && month === today.getMonth();

      const daysArray = generateDateTable(year, month);

      titleEl.textContent = months[month] + ' ' + year;
      tbody.innerHTML = '';

      // First day offset (Sun=0)
      const firstDay = daysArray[0].weekday;

      let row = document.createElement('tr');
      let col = 0;

      // Padding days
      for (let i = 0; i < firstDay; i++) {
        const td = document.createElement('td');
        row.appendChild(td);
        col++;
      }

      // Days
      for (let day = 1; day <= daysInMonth; day++) {
        if (col === 7) {
          tbody.appendChild(row);
          row = document.createElement('tr');
          col = 0;
        }

        const td = document.createElement('td');
        td.textContent = day;
        td.style.padding = '12px 4px';
        td.style.textAlign = 'center';
        td.style.fontWeight = '500';
        td.style.cursor = 'pointer';
        td.style.transition = 'all 0.2s';
        td.style.borderRadius = '6px';

        // Today
        if (isCurrent && day === today.getDate()) {
          td.style.backgroundColor = '#fef08a';
          td.style.color = '#92400e';
          td.style.fontWeight = 'bold';
        }

        // Holiday
        const isHoliday = holidays.some(holiday => {
          const hDate = new Date(holiday.date);
          return hDate.getFullYear() === year && 
              hDate.getMonth() === month && 
              hDate.getDate() === day;
        });

        if (isHoliday) {
          td.style.backgroundColor = '#fee2e2';
          td.style.color = '#dc2626';
          td.style.fontWeight = 'bold';
          td.title = 'National Holiday';
        }

        td.addEventListener('mouseenter', () => {
          if (!td.style.backgroundColor) td.style.backgroundColor = '#f3f4f6';
          td.style.transform = 'scale(1.1)';
        });
        td.addEventListener('mouseleave', () => {
          td.style.transform = '';
        });

        row.appendChild(td);
        col++;
      }

      // Padding end
      while (col % 7 !== 0) {
        const td = document.createElement('td');
        row.appendChild(td);
        col++;
      }

      tbody.appendChild(row);
    }

    prevBtn.addEventListener('click', () => {
      currentMonth--;
      if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
      }
      render();
    });

    nextBtn.addEventListener('click', () => {
      currentMonth++;
      if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
      }
      render();
    });

    render();
  });
});
