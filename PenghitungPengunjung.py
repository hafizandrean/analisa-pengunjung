"""
Program Utama Pendeteksi dan Penghitung Pengunjung (People Counter)
menggunakan OpenCV dan Background Subtraction (MOG2).
"""

import os
import time
import cv2 as cv
import numpy as np
import Person


def main():
    # Path file video sumber dan file log
    video_source = 'video sample.mp4'
    if not os.path.exists(video_source):
        video_source = 'Test Files/3401.avi'
    log_file_path = 'log.txt'

    if not os.path.exists(video_source):
        print(f"Peringatan: File video '{video_source}' tidak ditemukan.")
        print("Silakan sesuaikan variabel 'video_source' dengan path video Anda atau gunakan 0 untuk webcam.")

    cap = cv.VideoCapture(video_source)
    if not cap.isOpened():
        print(f"Error: Tidak dapat membuka sumber video '{video_source}'.")
        return

    # Membuka file log untuk mencatat aktivitas
    try:
        log = open(log_file_path, "a", encoding="utf-8")
    except IOError:
        print(f"Error: Tidak dapat membuka file log '{log_file_path}'.")
        log = None

    # Counter penghitung masuk & keluar
    cnt_up = 0
    cnt_down = 0

    # Dimensi frame dasar
    h = 480
    w = 640
    frame_area = h * w
    area_th = frame_area / 250
    print(f"Area Threshold: {area_th}")

    # Garis batas melintas (Entry & Exit lines)
    line_up = int(2 * (h / 5))
    line_down = int(3 * (h / 5))

    up_limit = int(1 * (h / 5))
    down_limit = int(4 * (h / 5))

    print(f"Garis Merah (Down/Masuk) y: {line_down}")
    print(f"Garis Biru (Up/Keluar) y  : {line_up}")

    # Titik garis untuk visualisasi di OpenCV
    pts_l1 = np.array([[0, line_down], [w, line_down]], np.int32).reshape((-1, 1, 2))
    pts_l2 = np.array([[0, line_up], [w, line_up]], np.int32).reshape((-1, 1, 2))
    pts_l3 = np.array([[0, up_limit], [w, up_limit]], np.int32).reshape((-1, 1, 2))
    pts_l4 = np.array([[0, down_limit], [w, down_limit]], np.int32).reshape((-1, 1, 2))

    line_down_color = (255, 0, 0)  # Biru (BGR)
    line_up_color = (0, 0, 255)    # Merah (BGR)

    # Subtraksi Background & Elemen Morfologi
    fgbg = cv.createBackgroundSubtractorMOG2(detectShadows=True)
    kernel_op = np.ones((3, 3), np.uint8)
    kernel_cl = np.ones((11, 11), np.uint8)

    # Pengaturan Objek Tracking
    font = cv.FONT_HERSHEY_SIMPLEX
    persons = []
    max_p_age = 5
    pid = 1

    try:
        while cap.isOpened():
            ret, frame = cap.read()
            if not ret or frame is None:
                # Video selesai, reset kembali ke frame awal (looping)
                cap.set(cv.CAP_PROP_POS_FRAMES, 0)
                ret, frame = cap.read()
                if not ret or frame is None:
                    print("EOF (End of File) atau pemrosesan video selesai.")
                    break

            # Tambah umur (frame count) setiap person
            for person in persons:
                person.age_one()

            # ----------------------------------------------------
            # PRE-PROCESSING (Background Subtraction & Morphology)
            # ----------------------------------------------------
            fgmask = fgbg.apply(frame)

            # Thresholding untuk menghilangkan bayangan
            _, im_bin = cv.threshold(fgmask, 200, 255, cv.THRESH_BINARY)
            # Opening (Erosi -> Dilasi) untuk menghilangkan noise
            mask = cv.morphologyEx(im_bin, cv.MORPH_OPEN, kernel_op)
            # Closing (Dilasi -> Erosi) untuk menggabungkan area putih
            mask = cv.morphologyEx(mask, cv.MORPH_CLOSE, kernel_cl)

            # ----------------------------------------------------
            # DETEKSI KONTUR & TRACKING
            # ----------------------------------------------------
            contours0, _ = cv.findContours(mask, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE)

            for cnt in contours0:
                area = cv.contourArea(cnt)
                if area > area_th:
                    m = cv.moments(cnt)
                    if m['m00'] == 0:
                        continue
                    cx = int(m['m10'] / m['m00'])
                    cy = int(m['m01'] / m['m00'])
                    x, y, bw, bh = cv.boundingRect(cnt)

                    new_person = True
                    if up_limit <= cy <= down_limit:
                        for person in persons:
                            if abs(cx - person.getX()) <= bw and abs(cy - person.getY()) <= bh:
                                new_person = False
                                person.updateCoords(cx, cy)

                                # Cek pergerakan ke atas (Keluar)
                                if person.going_UP(line_down, line_up):
                                    cnt_up += 1
                                    timestamp = time.strftime("%c")
                                    log_msg = f"ID: {person.getId()} crossed going up at {timestamp}"
                                    print(log_msg)
                                    if log:
                                        log.write(log_msg + '\n')

                                # Cek pergerakan ke bawah (Masuk)
                                elif person.going_DOWN(line_down, line_up):
                                    cnt_down += 1
                                    timestamp = time.strftime("%c")
                                    log_msg = f"ID: {person.getId()} crossed going down at {timestamp}"
                                    print(log_msg)
                                    if log:
                                        log.write(log_msg + '\n')
                                break

                        if new_person:
                            p = Person.MyPerson(pid, cx, cy, max_p_age)
                            persons.append(p)
                            pid += 1

                    # Visualisasi penanda objek
                    cv.circle(frame, (cx, cy), 5, (0, 0, 255), -1)
                    cv.rectangle(frame, (x, y), (x + bw, y + bh), (0, 255, 0), 2)

            # ----------------------------------------------------
            # EVALUASI STATUS & CLEANUP PERSON EXPIRED
            # ----------------------------------------------------
            for person in persons:
                if person.getState() == '1':
                    if person.getDir() == 'down' and person.getY() > down_limit:
                        person.setDone()
                    elif person.getDir() == 'up' and person.getY() < up_limit:
                        person.setDone()

                # Tampilkan ID objek pada frame
                cv.putText(frame, str(person.getId()), (person.getX(), person.getY()),
                           font, 0.4, person.getRGB(), 1, cv.LINE_AA)

            # Filter aman untuk menghapus person yang timed out / done
            persons = [p for p in persons if not p.timedOut()]

            # ----------------------------------------------------
            # VISUALISASI GARIS DAN TEKS COUNTER
            # ----------------------------------------------------
            str_up = f"Keluar: {cnt_up}"
            str_down = f"Masuk: {cnt_down}"

            cv.polylines(frame, [pts_l1], False, line_down_color, thickness=2)
            cv.polylines(frame, [pts_l2], False, line_up_color, thickness=2)
            cv.polylines(frame, [pts_l3], False, (255, 255, 255), thickness=1)
            cv.polylines(frame, [pts_l4], False, (255, 255, 255), thickness=1)

            # Outline teks untuk keterbacaan yang lebih baik
            cv.putText(frame, str_up, (10, 40), font, 0.6, (0, 0, 0), 3, cv.LINE_AA)
            cv.putText(frame, str_up, (10, 40), font, 0.6, (0, 0, 255), 1, cv.LINE_AA)
            cv.putText(frame, str_down, (10, 90), font, 0.6, (0, 0, 0), 3, cv.LINE_AA)
            cv.putText(frame, str_down, (10, 90), font, 0.6, (255, 0, 0), 1, cv.LINE_AA)

            cv.imshow('Frame Utama', frame)
            cv.imshow('Masking', mask)

            # Keluar jika menekan tombol ESC (ASCII 27)
            k = cv.waitKey(30) & 0xFF
            if k == 27:
                print("Program dihentikan oleh pengguna.")
                break

    finally:
        print("\n=== Ringkasan Hasil ===")
        print(f"Keluar: {cnt_up}")
        print(f"Masuk : {cnt_down}")

        if log:
            log.flush()
            log.close()

        cap.release()
        cv.destroyAllWindows()


if __name__ == '__main__':
    main()
