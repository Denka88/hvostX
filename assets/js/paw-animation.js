/**
 * Анимация кошачьих следов на фоне
 * Все следы создаются сразу на фиксированных позициях страницы
 * При скролле вниз - следы появляются, когда центр экрана проходит их позицию
 * При скролле вверх - следы исчезают в обратном порядке
 */

document.addEventListener('DOMContentLoaded', function() {
    // Отключаем на мобильных
    if (window.innerWidth <= 768) {
        return;
    }

    // Создаем контейнер для следов
    const container = document.createElement('div');
    container.className = 'paw-tracks-container';
    container.id = 'pawTracksContainer';
    document.body.insertBefore(container, document.body.firstChild);

    // SVG кошачьей лапки
    const pawSvg = `
        <svg viewBox="0 0 21000 29700" xmlns="http://www.w3.org/2000/svg">
            <path d="M11006.2 11783.07c-1068.32,263.53 -672.62,1109.1 -1852.19,2063.86 -244.39,197.82 -436.04,366.41 -590.98,723.68 -319.37,736.39 77.79,1531.39 629.8,1750.48 862.11,342.1 1459.79,-313.58 2200.29,-188.88 657.49,110.74 1750.95,738.72 2371.28,-324.49 287.11,-492.06 201.47,-1085.88 -92.33,-1515.84 -141.74,-207.48 -297.25,-333.13 -478.07,-478.15 -349.94,-280.66 -527.57,-496.05 -750.86,-902.81 -75.11,-136.83 -129.04,-287.56 -204.92,-432.15 -72.05,-137.22 -186.97,-304.43 -271.18,-385.75 -186.15,-179.69 -562.7,-408.19 -960.84,-309.95z"/>
            <path d="M12563.07 8407.49c-573.89,130.7 -921.33,878.46 -999.78,1568.16 -44.78,393.58 29.95,806.67 224.73,1060.96 160.12,209.04 456.31,457.02 874.7,400.22 1535.24,-208.48 1150.96,-3314.19 -99.65,-3029.34z"/>
            <path d="M9530.74 8415.25c-831.57,190.59 -1106.56,1904.83 -424.27,2693.36 169.35,195.72 535.59,398.99 926.89,312.57 752.34,-166.19 895.75,-1023.1 751.87,-1710.4 -124.92,-596.77 -558.67,-1455.01 -1254.49,-1295.53z"/>
            <path d="M14546.13 10884.09c-776.01,222.31 -1443.43,1591.79 -1003.12,2321.45 137.39,227.67 402.69,441.06 800.09,410.4 1003.54,-77.39 1335.31,-1634.87 995.92,-2344.93 -119.7,-250.4 -395.3,-500.81 -792.89,-386.92z"/>
            <path d="M7544.92 10869.92c-1152.06,240.31 -612.17,3114.76 827.12,2711.88 639.73,-179.09 756.55,-990.93 463.32,-1644.87 -183.27,-408.69 -432.17,-787.52 -809.09,-983.78 -137.41,-71.53 -303.93,-120.24 -481.35,-83.23z"/>
        </svg>
    `;

    // Параметры
    const config = {
        spacing: 250,          // Расстояние между парами следов
        leftOffset: '5%',      // Позиция левых следов
        rightOffset: '5%',     // Позиция правых следов
        leftRotation: 165,     // Поворот левой лапки
        rightRotation: 195,    // Поворот правой лапки
        rotationRandom: 30,    // Случайное отклонение поворота (±15°)
        xOffsetRandom: 20,     // Случайное смещение по горизонтали (±10%)
        yOffsetRandom: 20,     // Случайное смещение по вертикали (±10px)
    };

    // Все следы на странице
    let allTracks = [];
    let lastScrollTop = 0;
    let lastVisibleIndex = -1;

    /**
     * Создаёт все следы на странице сразу
     */
    function createAllTracks() {
        const docHeight = document.documentElement.scrollHeight;
        const numTracks = Math.ceil(docHeight / config.spacing);

        for (let i = 0; i < numTracks; i++) {
            const y = i * config.spacing + 100;
            const side = i % 2 === 0 ? 'left' : 'right';

            // Создаём элемент
            const track = document.createElement('div');
            track.className = `paw-track ${side}`;
            track.innerHTML = pawSvg;
            track.style.top = y + 'px';

            // Случайные смещения для разнообразия
            const randomRotation = (Math.random() - 0.5) * config.rotationRandom;
            const randomOffset = (Math.random() - 0.5) * config.xOffsetRandom;
            const randomYOffset = (Math.random() - 0.5) * config.yOffsetRandom;

            const baseRotation = side === 'left' ? config.leftRotation : config.rightRotation;
            track.style.transform = `rotate(${baseRotation + randomRotation}deg) translateX(${randomOffset}%) translateY(${randomYOffset}px)`;

            container.appendChild(track);

            // Сохраняем информацию о следе
            allTracks.push({
                element: track,
                y: y,
                side: side,
                index: i,
                visible: false
            });
        }
    }

    /**
     * Обновляет видимость следов на основе позиции скролла
     */
    function updateTracksVisibility() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const viewportHeight = window.innerHeight;
        const centerScrollY = scrollTop + viewportHeight / 2;

        // Находим индекс последнего следа, который должен быть виден
        // След виден, если его Y <= центру экрана (с небольшим запасом)
        let newVisibleIndex = -1;
        for (let i = 0; i < allTracks.length; i++) {
            if (allTracks[i].y <= centerScrollY - 50) {
                newVisibleIndex = i;
            } else {
                break;
            }
        }

        // Если скроллим вниз - показываем новые следы
        if (newVisibleIndex > lastVisibleIndex) {
            for (let i = lastVisibleIndex + 1; i <= newVisibleIndex; i++) {
                if (allTracks[i]) {
                    allTracks[i].visible = true;
                    requestAnimationFrame(() => {
                        allTracks[i].element.classList.add('visible');
                    });
                }
            }
        }
        // Если скроллим вверх - скрываем следы
        else if (newVisibleIndex < lastVisibleIndex) {
            for (let i = lastVisibleIndex; i > newVisibleIndex; i--) {
                if (allTracks[i]) {
                    allTracks[i].visible = false;
                    allTracks[i].element.classList.remove('visible');
                }
            }
        }

        lastVisibleIndex = newVisibleIndex;
        lastScrollTop = scrollTop;
    }

    /**
     * Обновляет позицию контейнера (height)
     */
    function updateContainerHeight() {
        const docHeight = document.documentElement.scrollHeight;
        container.style.height = docHeight + 'px';
    }

    // Инициализация
    createAllTracks();
    updateContainerHeight();
    
    // Сразу показываем следы, которые уже в видимой области
    const initialScrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const viewportHeight = window.innerHeight;
    const initialCenter = initialScrollTop + viewportHeight / 2;
    
    allTracks.forEach((track, i) => {
        if (track.y <= initialCenter - 50) {
            track.visible = true;
            track.element.classList.add('visible');
            lastVisibleIndex = i;
        }
    });

    // Обработчик скролла
    let ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                updateTracksVisibility();
                updateContainerHeight();
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // Ресайз окна
    window.addEventListener('resize', function() {
        updateContainerHeight();
    });
});
