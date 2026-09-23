<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class DocumentUploader
{
    public function __construct(
        private string $documentsDirectory,
        private SluggerInterface $slugger,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        $file->move($this->documentsDirectory, $newFilename);

        return $newFilename;
    }

    public function getFilePath(string $filename): string
    {
        return $this->documentsDirectory.'/'.$filename;
    }

    public function remove(string $filename): void
    {
        $filePath = $this->getFilePath($filename);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}