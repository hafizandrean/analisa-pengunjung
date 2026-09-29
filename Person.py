"""
Module untuk mengelola data dan tracking objek orang (Person tracking).
"""
import random


class MyPerson:
    def __init__(self, person_id: int, x: int, y: int, max_age: int):
        self.i = person_id
        self.x = x
        self.y = y
        self.tracks = []
        self.R = random.randint(0, 255)
        self.G = random.randint(0, 255)
        self.B = random.randint(0, 255)
        self.done = False
        self.state = '0'
        self.age = 0
        self.max_age = max_age
        self.dir = None

    def getRGB(self):
        return (self.R, self.G, self.B)

    def getTracks(self):
        return self.tracks

    def getId(self):
        return self.i

    def getState(self):
        return self.state

    def getDir(self):
        return self.dir

    def getX(self):
        return self.x

    def getY(self):
        return self.y

    def updateCoords(self, xn: int, yn: int):
        self.age = 0
        self.tracks.append([self.x, self.y])
        self.x = xn
        self.y = yn

    def setDone(self):
        self.done = True

    def timedOut(self):
        return self.done

    def going_UP(self, mid_start: int, mid_end: int) -> bool:
        """Mengecek apakah objek bergerak melintasi garis batas atas."""
        if len(self.tracks) >= 2:
            if self.state == '0':
                if self.tracks[-1][1] < mid_end and self.tracks[-2][1] >= mid_end:
                    self.state = '1'
                    self.dir = 'up'
                    return True
        return False

    def going_DOWN(self, mid_start: int, mid_end: int) -> bool:
        """Mengecek apakah objek bergerak melintasi garis batas bawah."""
        if len(self.tracks) >= 2:
            if self.state == '0':
                if self.tracks[-1][1] > mid_start and self.tracks[-2][1] <= mid_start:
                    self.state = '1'
                    self.dir = 'down'
                    return True
        return False

    def age_one(self) -> bool:
        """Menambah umur (frame counter) objek."""
        self.age += 1
        if self.age > self.max_age:
            self.done = True
        return True
