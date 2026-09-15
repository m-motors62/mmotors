<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class VehiculePhotoUploader
{
    public function __construct(
        private string $vehiculePhotosDirectory,
        private SluggerInterface $slugger,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        $file->move($this->vehiculePhotosDirectory, $newFilename);

        return $newFilename;
    }

    public function remove(string $filename): void
    {
        $filePath = $this->vehiculePhotosDirectory.'/'.$filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}